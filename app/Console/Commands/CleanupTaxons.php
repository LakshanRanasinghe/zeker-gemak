<?php

namespace App\Console\Commands;

use App\Services\SearchIndexInvalidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Vanilo\Foundation\Models\Taxon;
use Vanilo\Translation\Models\Translation;

/**
 * CleanupTaxons Command
 *
 * Cleans up categories (taxons) after product import.
 *
 * This command:
 * - Removes duplicate taxons (same taxonomy_id + slug)
 * - Removes unused taxons (not linked to any product)
 *
 * It does NOT:
 * - Normalize or modify slugs
 * - Rename or merge categories
 *
 * Duplicate detection criteria:
 * - Same taxonomy_id
 * - Same slug (case-insensitive)
 * - Keeps the oldest record, removes newer duplicates
 *
 * Run this AFTER product import completes.
 */
class CleanupTaxons extends Command
{
    private const TAXON_MORPH_TYPE = 'taxon';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-taxons
                            {--dry-run : Show what would be cleaned without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate and unused categories after product import';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║        Taxon Cleanup - Duplicate Removal         ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $stats = [
            'duplicates_removed' => 0,
            'unused_removed' => 0,
            'english_translations_normalized' => 0,
        ];

        // Remove duplicate taxons
        $this->comment('Finding and removing duplicate categories...');
        $this->removeDuplicateTaxons($stats, $dryRun);
        $this->newLine();

        // Remove unused taxons
        $this->comment('Finding and removing unused categories (no products attached)...');
        $this->removeUnusedTaxons($stats, $dryRun);
        $this->newLine();

        $this->comment('Ensuring categories have English names and slugs...');
        $this->normalizeEnglishTranslations($stats, $dryRun);
        $this->newLine();

        // Summary
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║              Cleanup Complete!                    ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        $this->line('Summary:');
        $this->line("  • Duplicate categories removed: {$stats['duplicates_removed']}");
        $this->line("  • Unused categories removed:    {$stats['unused_removed']}");
        $this->line("  • EN translations normalized:   {$stats['english_translations_normalized']}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('Run without --dry-run to apply these changes');
        }

        return self::SUCCESS;
    }

    /**
     * Remove taxons that have no product (or any model) associations.
     */
    private function removeUnusedTaxons(array &$stats, bool $dryRun): void
    {
        $unusedTaxons = DB::table('taxons')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('model_taxons')
                    ->whereColumn('model_taxons.taxon_id', 'taxons.id');
            })
            ->get(['id', 'name', 'slug']);

        if ($unusedTaxons->isEmpty()) {
            $this->info('✓ No unused categories found');

            return;
        }

        $this->line("  Found {$unusedTaxons->count()} unused categories:");
        $this->newLine();

        foreach ($unusedTaxons as $taxon) {
            $this->line("  • <fg=yellow>ID {$taxon->id}</> — {$taxon->name} (<fg=cyan>{$taxon->slug}</>)");
        }

        $this->newLine();

        if (! $dryRun) {
            $ids = $unusedTaxons->pluck('id')->all();

            DB::transaction(function () use ($ids, &$stats) {
                // woocommerce_category_taxon_mappings has ON DELETE CASCADE,
                // but we delete explicitly for clarity.
                DB::table('woocommerce_category_taxon_mappings')
                    ->whereIn('taxon_id', $ids)
                    ->delete();

                $this->deleteTaxonTranslations($ids);

                $deleted = Taxon::query()->whereIn('id', $ids)->delete();
                $stats['unused_removed'] += $deleted;
            });

            $this->info("✓ Removed {$stats['unused_removed']} unused categories");
        } else {
            $stats['unused_removed'] += $unusedTaxons->count();
            $this->warn("  [DRY RUN] Would remove {$stats['unused_removed']} unused categories");
        }
    }

    /**
     * Remove duplicate taxons based on taxonomy_id and slug.
     *
     * Strategy:
     * - Find taxons with the same taxonomy_id and slug (case-insensitive)
     * - Keep the oldest one (lowest ID)
     * - Delete the newer duplicates
     * - Update any references in model_taxons to point to the kept taxon
     */
    private function removeDuplicateTaxons(array &$stats, bool $dryRun): void
    {
        // Find duplicate groups
        $duplicateGroups = DB::table('taxons')
            ->select(
                'taxonomy_id',
                DB::raw('LOWER(slug) as slug_lower'),
                DB::raw('GROUP_CONCAT(id ORDER BY id ASC) as ids'),
                DB::raw('GROUP_CONCAT(name ORDER BY id ASC) as names'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('taxonomy_id', DB::raw('LOWER(slug)'))
            ->having('count', '>', 1)
            ->get();

        if ($duplicateGroups->isEmpty()) {
            $this->info('✓ No duplicate categories found');

            return;
        }

        $this->line("  Found {$duplicateGroups->count()} duplicate category groups:");
        $this->newLine();

        foreach ($duplicateGroups as $group) {
            $ids = explode(',', $group->ids);
            $names = explode(',', $group->names);
            $keepId = (int) $ids[0];
            $removeIds = array_map('intval', array_slice($ids, 1));

            $this->line("  • Slug: <fg=cyan>{$group->slug_lower}</>");
            $this->line("    Keep: ID {$keepId} ({$names[0]})");
            $this->line('    Remove: '.implode(', ', array_map(fn ($id, $name) => "ID {$id} ({$name})", $removeIds, array_slice($names, 1))));
            $this->newLine();

            if (! $dryRun) {
                DB::transaction(function () use ($keepId, $removeIds, &$stats) {
                    // Update any product/model relationships to point to the kept taxon
                    DB::table('model_taxons')
                        ->whereIn('taxon_id', $removeIds)
                        ->update(['taxon_id' => $keepId]);

                    // Remove from WooCommerce mapping table
                    DB::table('woocommerce_category_taxon_mappings')
                        ->whereIn('taxon_id', $removeIds)
                        ->delete();

                    $this->deleteTaxonTranslations($removeIds);

                    // Delete the duplicate taxons
                    $deleted = Taxon::query()->whereIn('id', $removeIds, 'and', false)->delete();

                    $stats['duplicates_removed'] += $deleted;
                });

                app(SearchIndexInvalidator::class)->reindexForTaxons([$keepId]);
            } else {
                $stats['duplicates_removed'] += count($removeIds);
            }
        }

        if (! $dryRun) {
            $this->info("✓ Removed {$stats['duplicates_removed']} duplicate categories");
        } else {
            $this->warn("  [DRY RUN] Would remove {$stats['duplicates_removed']} duplicate categories");
        }
    }

    /**
     * Ensure every retained taxon has an English translation row with a name
     * and slug. This is temporary fallback data while WordPress translations
     * are incomplete; existing English values are preserved.
     */
    private function normalizeEnglishTranslations(array &$stats, bool $dryRun): void
    {
        $taxons = Taxon::query()
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);

        $reservedSlugs = $this->existingEnglishTranslationSlugs();

        $normalizations = $taxons
            ->map(function (Taxon $taxon) use (&$reservedSlugs): ?array {
                $translation = $this->findEnglishTranslation($taxon);
                $nameNeedsFallbackTranslation = $this->shouldTranslateFallbackName($translation, $taxon);

                $name = $nameNeedsFallbackTranslation
                    ? $this->translateFallbackName((string) $taxon->name)
                    : (string) $translation->name;

                if (! filled($name)) {
                    $name = (string) $taxon->name;
                }

                $slug = ($nameNeedsFallbackTranslation || $this->shouldTranslateFallbackSlug($translation, $taxon))
                    ? $this->uniqueEnglishTranslationSlug($taxon, $name !== '' ? $name : (string) $taxon->slug, $reservedSlugs)
                    : (string) $translation->slug;

                if (
                    $translation !== null
                    && (string) $translation->name === $name
                    && (string) $translation->slug === $slug
                ) {
                    return null;
                }

                $reservedSlugs[$slug] = (int) $taxon->getKey();

                return [
                    'taxon' => $taxon,
                    'translation' => $translation,
                    'name' => $name,
                    'slug' => $slug,
                ];
            })
            ->filter()
            ->values();

        if ($normalizations->isEmpty()) {
            $this->info('✓ All retained categories already have English names and slugs');

            return;
        }

        $this->line("  Found {$normalizations->count()} categories with missing or fallback English data:");
        $this->newLine();

        foreach ($normalizations as $normalization) {
            /** @var Taxon $taxon */
            $taxon = $normalization['taxon'];

            $this->line("  • ID {$taxon->id} — {$taxon->name}");
            $this->line("    EN name: <fg=cyan>{$normalization['name']}</>");
            $this->line("    EN slug: <fg=cyan>{$normalization['slug']}</>");
            $this->newLine();
        }

        if (! $dryRun) {
            DB::transaction(function () use ($normalizations, &$stats) {
                foreach ($normalizations as $normalization) {
                    /** @var Taxon $taxon */
                    $taxon = $normalization['taxon'];
                    /** @var Translation|null $translation */
                    $translation = $normalization['translation'];

                    if ($translation !== null) {
                        $translation->update([
                            'name' => $normalization['name'],
                            'slug' => $normalization['slug'],
                            'fields' => $translation->fields ?? [],
                        ]);
                    } else {
                        Translation::query()->create([
                            'translatable_type' => self::TAXON_MORPH_TYPE,
                            'translatable_id' => $taxon->getKey(),
                            'language' => 'en',
                            'name' => $normalization['name'],
                            'slug' => $normalization['slug'],
                            'fields' => [],
                        ]);
                    }

                    $stats['english_translations_normalized']++;
                }
            });

            $this->info("✓ Normalized {$stats['english_translations_normalized']} English category translations");
        } else {
            $stats['english_translations_normalized'] += $normalizations->count();
            $this->warn("  [DRY RUN] Would normalize {$stats['english_translations_normalized']} English category translations");
        }
    }

    private function findEnglishTranslation(Taxon $taxon): ?Translation
    {
        return Translation::query()
            ->where('translatable_type', self::TAXON_MORPH_TYPE)
            ->where('translatable_id', $taxon->getKey())
            ->where('language', 'en')
            ->first();
    }

    private function shouldTranslateFallbackName(?Translation $translation, Taxon $taxon): bool
    {
        if (! filled($translation?->name)) {
            return true;
        }

        return $this->normalizedText((string) $translation->name) === $this->normalizedText((string) $taxon->name);
    }

    private function shouldTranslateFallbackSlug(?Translation $translation, Taxon $taxon): bool
    {
        if (! filled($translation?->slug)) {
            return true;
        }

        return $this->normalizedText((string) $translation->slug) === $this->normalizedText((string) $taxon->slug);
    }

    private function translateFallbackName(string $name): string
    {
        $translated = html_entity_decode($name, ENT_QUOTES | ENT_HTML5);

        $exactTranslations = [
            'Labelprinters' => 'Label printers',
            'Vervallen producten (archief en alternatieven)' => 'Discontinued products (archive and alternatives)',
            'Starterkits' => 'Starter kits',
            'Etiketten' => 'Labels',
            'Thermal labelprinters' => 'Thermal label printers',
            'Desktop Labelprinters' => 'Desktop Label printers',
            'Midrange Labelprinters' => 'Midrange Label printers',
            'Inkt cartridges' => 'Ink cartridges',
            'Thermisch Directe printer media' => 'Direct Thermal printer media',
            'Thermische Overdracht printer media' => 'Thermal Transfer printer media',
            'Juweliersetiketten' => 'Jewellery labels',
            'Wijn labels' => 'Wine labels',
            'Bierfles labels' => 'Beer bottle labels',
            'Bakkers & chocolaterie' => 'Bakeries & chocolate shops',
            'Banderol' => 'Banderole',
            'Verzendetiketten' => 'Shipping labels',
        ];

        if (isset($exactTranslations[$translated])) {
            return $exactTranslations[$translated];
        }

        $replacements = [
            'Thermisch Directe' => 'Direct Thermal',
            'Thermische Overdracht' => 'Thermal Transfer',
            'Labelprinters' => 'Label printers',
            'labelprinters' => 'label printers',
            'Labels en tickets' => 'Labels and tickets',
            'Etiketten' => 'Labels',
            'Juweliersetiketten' => 'Jewellery labels',
            'Verzendetiketten' => 'Shipping labels',
            'Wijn labels' => 'Wine labels',
            'Bierfles labels' => 'Beer bottle labels',
            'Inkt cartridges' => 'Ink cartridges',
            'Starterkits' => 'Starter kits',
            'Bakkers & chocolaterie' => 'Bakeries & chocolate shops',
            'Banderol' => 'Banderole',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $translated);
    }

    private function normalizedText(string $value): string
    {
        return Str::of(html_entity_decode($value, ENT_QUOTES | ENT_HTML5))
            ->lower()
            ->squish()
            ->toString();
    }

    /**
     * @param  array<string, int>  $reservedSlugs
     */
    private function uniqueEnglishTranslationSlug(Taxon $taxon, string $source, array $reservedSlugs): string
    {
        $baseSlug = Str::slug($source);

        if ($baseSlug === '') {
            $baseSlug = 'category-'.$taxon->getKey();
        }

        if (! $this->englishTranslationSlugIsReservedForAnotherTaxon($taxon, $baseSlug, $reservedSlugs)) {
            return $baseSlug;
        }

        $suffix = (string) $taxon->getKey();
        $candidate = "{$baseSlug}-{$suffix}";
        $attempt = 2;

        while ($this->englishTranslationSlugIsReservedForAnotherTaxon($taxon, $candidate, $reservedSlugs)) {
            $candidate = "{$baseSlug}-{$suffix}-{$attempt}";
            $attempt++;
        }

        return $candidate;
    }

    /**
     * @return array<string, int>
     */
    private function existingEnglishTranslationSlugs(): array
    {
        return Translation::query()
            ->where('translatable_type', self::TAXON_MORPH_TYPE)
            ->where('language', 'en')
            ->whereNotNull('slug')
            ->pluck('translatable_id', 'slug')
            ->map(fn (mixed $taxonId): int => (int) $taxonId)
            ->all();
    }

    /**
     * @param  array<string, int>  $reservedSlugs
     */
    private function englishTranslationSlugIsReservedForAnotherTaxon(Taxon $taxon, string $slug, array $reservedSlugs): bool
    {
        $ownerId = $reservedSlugs[$slug] ?? null;

        return $ownerId !== null && $ownerId !== (int) $taxon->getKey();
    }

    /**
     * @param  array<int, int>  $taxonIds
     */
    private function deleteTaxonTranslations(array $taxonIds): void
    {
        Translation::query()
            ->where('translatable_type', self::TAXON_MORPH_TYPE)
            ->whereIn('translatable_id', $taxonIds)
            ->delete();
    }
}
