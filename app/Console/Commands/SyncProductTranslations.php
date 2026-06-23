<?php

namespace App\Console\Commands;

use App\Models\MasterProduct;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Vanilo\Translation\Models\Translation;

class SyncProductTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-product-translations
                            {--chunk=500 : Number of records to process per chunk}
                            {--no-reindex : Normalize translations without updating the search index}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Normalize product translation payloads and reindex products';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $shouldReindex = ! (bool) $this->option('no-reindex');

        $this->info('Syncing product translations...');

        $normalized = $this->normalizeTranslations($chunkSize);

        $this->line("Normalized {$normalized} translation records.");

        if ($shouldReindex) {
            $this->reindexProducts();
        }

        $this->info('Product translation sync complete.');

        return self::SUCCESS;
    }

    private function normalizeTranslations(int $chunkSize): int
    {
        $normalized = 0;

        Translation::query()
            ->whereIn('translatable_type', [
                morph_type_of(Product::class),
                morph_type_of(MasterProduct::class),
            ])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($translations) use (&$normalized): void {
                foreach ($translations as $translation) {
                    $fields = $this->normalizedFields($translation->fields);

                    if ($fields === null) {
                        continue;
                    }

                    $translation->forceFill(['fields' => $fields])->save();
                    $normalized++;
                }
            });

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $fields
     * @return array<string, mixed>|null
     */
    private function normalizedFields(?array $fields): ?array
    {
        if (! is_array($fields) || ! isset($fields['fields']) || ! is_array($fields['fields'])) {
            return null;
        }

        return array_merge(
            collect($fields)->except('fields')->all(),
            $fields['fields'],
        );
    }

    private function reindexProducts(): void
    {
        foreach ($this->searchableModels() as $name => $modelClass) {
            $count = $modelClass::query()->count();

            if ($count === 0) {
                $this->line("No {$name} records to reindex.");

                continue;
            }

            $this->line("Reindexing {$count} {$name} records...");
            $modelClass::query()->searchable();
        }
    }

    /**
     * @return array<string, class-string<Model>>
     */
    private function searchableModels(): array
    {
        return [
            'Product' => Product::class,
            'MasterProduct' => MasterProduct::class,
        ];
    }
}
