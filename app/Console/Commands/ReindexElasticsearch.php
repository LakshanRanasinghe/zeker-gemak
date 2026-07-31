<?php

namespace App\Console\Commands;

use App\Models\GroupProduct;
use App\Models\Product;
use Illuminate\Console\Command;

class ReindexElasticsearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reindex-elasticsearch 
                            {--flush : Only flush indexes without reimporting}
                            {--import : Only import without flushing}
                            {--model=* : Specific models to reindex (Product, GroupProduct)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush and reindex product Elasticsearch indexes';

    private array $models = [
        'Product' => Product::class,
        // 'GroupProduct' => GroupProduct::class,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $flushOnly = $this->option('flush');
        $importOnly = $this->option('import');
        $specificModels = $this->option('model');

        // Determine which models to process
        $modelsToProcess = empty($specificModels)
            ? $this->models
            : collect($specificModels)
                ->mapWithKeys(fn ($name) => [$name => $this->models[$name] ?? null])
                ->filter()
                ->toArray();

        if (empty($modelsToProcess)) {
            $this->error('No valid models specified. Available: Product, GroupProduct');

            return self::FAILURE;
        }

        $this->info('🔄 Elasticsearch Reindexing');
        $this->newLine();

        // Flush phase
        if (! $importOnly) {
            $this->info('🗑️  Flushing indexes...');
            foreach ($modelsToProcess as $name => $class) {
                $this->flushModel($class, $name);
            }
            $this->newLine();
        }

        // Import phase
        if (! $flushOnly) {
            $this->info('📥 Importing data...');
            foreach ($modelsToProcess as $name => $class) {
                $this->importModel($class, $name);
            }
            $this->newLine();
        }

        $this->info('✅ Reindexing complete!');

        return self::SUCCESS;
    }

    private function flushModel(string $modelClass, string $name): void
    {
        $this->line("  Flushing {$name}...");

        try {
            $this->call('scout:flush', ['model' => $modelClass]);
        } catch (\Exception $e) {
            $this->warn("    ⚠️  Could not flush {$name}: {$e->getMessage()}");
        }
    }

    private function importModel(string $modelClass, string $name): void
    {
        $this->line("  Importing {$name}...");

        $count = $modelClass::query()->count();
        $this->line("    Found {$count} records");

        try {
            $this->call('scout:import', ['model' => $modelClass]);
            $this->info("    ✓ {$name} imported");
        } catch (\Exception $e) {
            $this->error("    ✗ {$name} import failed: {$e->getMessage()}");
        }
    }
}
