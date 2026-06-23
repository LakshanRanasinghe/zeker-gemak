<?php

namespace App\Jobs;

use App\Services\SearchIndexInvalidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vanilo\Foundation\Models\Taxon;

/**
 * CleanupTaxonsJob
 *
 * Cleans up duplicate taxons (categories) after product import.
 *
 * This job only removes duplicate taxons - it does NOT:
 * - Remove unused taxons (categories are kept even if not linked to products)
 * - Normalize or modify slugs
 * - Rename or merge categories
 *
 * Duplicate detection criteria:
 * - Same taxonomy_id
 * - Same slug (case-insensitive)
 * - Keeps the oldest record, removes newer duplicates
 *
 * This job should run AFTER product import completes.
 */
class CleanupTaxonsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('🧹 Starting taxon duplicate cleanup...');

        $stats = [
            'duplicates_removed' => 0,
        ];

        // Remove duplicate taxons
        $this->removeDuplicateTaxons($stats);

        Log::info('✅ Taxon duplicate cleanup completed', $stats);
    }

    /**
     * Remove duplicate taxons based on taxonomy_id and slug.
     *
     * Strategy:
     * - Find taxons with the same taxonomy_id and slug (case-insensitive)
     * - Keep the oldest one (lowest ID)
     * - Delete the newer duplicates
     * - Update any references in model_taxons to point to the kept taxon
     *
     * @param  array  $stats  Statistics array (passed by reference)
     */
    private function removeDuplicateTaxons(array &$stats): void
    {
        // Find duplicate groups
        // Group by taxonomy_id and LOWER(slug), count occurrences
        $duplicateGroups = DB::table('taxons')
            ->select(
                'taxonomy_id',
                DB::raw('LOWER(slug) as slug_lower'),
                DB::raw('GROUP_CONCAT(id ORDER BY id ASC) as ids'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('taxonomy_id', DB::raw('LOWER(slug)'))
            ->having('count', '>', 1)
            ->get();

        if ($duplicateGroups->isEmpty()) {
            Log::info('No duplicate taxons found.');

            return;
        }

        Log::info('Found '.count($duplicateGroups).' duplicate taxon groups to clean up');

        $keptTaxonIds = [];

        DB::transaction(function () use ($duplicateGroups, &$stats, &$keptTaxonIds) {
            foreach ($duplicateGroups as $group) {
                $ids = explode(',', $group->ids);
                $keepId = (int) $ids[0]; // Keep the oldest (lowest ID)
                $removeIds = array_map('intval', array_slice($ids, 1)); // Remove the rest

                if (empty($removeIds)) {
                    continue;
                }

                // Update any product/model relationships to point to the kept taxon
                DB::table('model_taxons')
                    ->whereIn('taxon_id', $removeIds)
                    ->update(['taxon_id' => $keepId]);

                // Remove from WooCommerce mapping table
                DB::table('woocommerce_category_taxon_mappings')
                    ->whereIn('taxon_id', $removeIds)
                    ->delete();

                // Delete the duplicate taxons
                $deleted = Taxon::query()->whereIn('id', $removeIds, 'and', false)->delete();

                $stats['duplicates_removed'] += $deleted;
                $keptTaxonIds[] = $keepId;
                Log::info("Removed {$deleted} duplicate taxons (kept ID: {$keepId}, removed IDs: ".implode(', ', $removeIds).')');
            }
        });

        app(SearchIndexInvalidator::class)->reindexForTaxons($keptTaxonIds);
    }
}
