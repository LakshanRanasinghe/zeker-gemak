<?php

namespace App\Services;

use App\Models\GroupProduct;
use App\Models\MasterProduct;
use App\Models\Post;
use App\Models\Product;
use App\Models\Taxon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;
use Vanilo\Translation\Models\Translation;

class SearchIndexInvalidator
{
    /**
     * @param  iterable<int, int|string|null>  $ids
     */
    public function reindexProducts(iterable $ids): void
    {
        $this->reindexSearchableByIds(Product::class, $ids);
    }

    /**
     * @param  iterable<int, int|string|null>  $ids
     */
    public function reindexMasterProducts(iterable $ids): void
    {
        $this->reindexSearchableByIds(MasterProduct::class, $ids);
    }

    /**
     * @param  iterable<int, int|string|null>  $ids
     */
    public function reindexGroupProducts(iterable $ids): void
    {
        $this->reindexSearchableByIds(GroupProduct::class, $ids);
    }

    /**
     * @param  iterable<int, int|string|null>  $ids
     */
    public function reindexPrinters(iterable $ids): void
    {
        $this->reindexSearchableByIds(Post::class, $ids, fn ($query) => $query->where('post_type', 'printer'));
    }

    public function reindexProduct(Product|int $product): void
    {
        $this->reindexProducts([$product instanceof Product ? $product->getKey() : $product]);
    }

    public function reindexPrinter(Post|int $printer): void
    {
        $this->reindexPrinters([$printer instanceof Post ? $printer->getKey() : $printer]);
    }

    public function reindexForProduct(Product $product): void
    {
        $this->reindexProduct($product);
    }

    public function reindexForPrinter(Post|int $printer): void
    {
        $printerId = $printer instanceof Post ? (int) $printer->getKey() : (int) $printer;

        $this->reindexPrinter($printerId);
        $this->reindexProducts(
            DB::table('printer_product')->where('printer_id', $printerId)->pluck('product_id')
        );
    }

    /**
     * @param  iterable<int, int|string|null>  $taxonIds
     */
    public function reindexForTaxons(iterable $taxonIds): void
    {
        $taxonIds = $this->normalizeIds($taxonIds);

        if ($taxonIds->isEmpty()) {
            return;
        }

        $allTaxonIds = $this->taxonIdsWithDescendants($taxonIds);

        $productIds = $this->modelTaxonIdsFor(Product::class, $allTaxonIds);
        $masterProductIds = $this->modelTaxonIdsFor(MasterProduct::class, $allTaxonIds);

        $this->reindexProducts($productIds);
        $this->reindexMasterProducts($masterProductIds);
        $this->reindexPrinters(
            DB::table('printer_product')->whereIn('product_id', $productIds)->pluck('printer_id')
        );
    }

    /**
     * @param  iterable<int, int|string|null>  $productIds
     * @param  iterable<int, int|string|null>  $masterProductIds
     */
    public function reindexTaxonAssignmentTargets(
        iterable $productIds,
        iterable $masterProductIds = []
    ): void {
        $productIds = $this->normalizeIds($productIds);

        $this->reindexProducts($productIds);
        $this->reindexMasterProducts($masterProductIds);
        $this->reindexPrinters(
            DB::table('printer_product')->whereIn('product_id', $productIds)->pluck('printer_id')
        );
    }

    public function reindexForTranslation(Translation $translation): void
    {
        $modelClass = $this->classForMorphType((string) $translation->translatable_type);
        $modelId = (int) $translation->translatable_id;

        match ($modelClass) {
            Product::class => $this->reindexProducts([$modelId]),
            MasterProduct::class => $this->reindexMasterProducts([$modelId]),
            GroupProduct::class => $this->reindexGroupProducts([$modelId]),
            Post::class => $this->reindexForPrinter($modelId),
            Taxon::class => $this->reindexForTaxons([$modelId]),
            default => null,
        };
    }

    /**
     * @param  iterable<int, int|string|null>  $productIds
     * @param  iterable<int, int|string|null>  $taxonIds
     */
    public function reindexAfterProductTaxonsChanged(iterable $productIds, iterable $taxonIds = []): void
    {
        $productIds = $this->normalizeIds($productIds);

        $this->reindexProducts($productIds);
        $this->reindexForTaxons($taxonIds);
        $this->reindexPrinters(
            DB::table('printer_product')->whereIn('product_id', $productIds)->pluck('printer_id')
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  iterable<int, int|string|null>  $ids
     */
    protected function reindexSearchableByIds(string $modelClass, iterable $ids, ?callable $queryCallback = null): void
    {
        $ids = $this->normalizeIds($ids);

        if ($ids->isEmpty()) {
            return;
        }

        try {
            $query = $modelClass::query()->whereKey($ids->all());

            if ($queryCallback !== null) {
                $queryCallback($query);
            }

            $query->get()->searchable();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  iterable<int, int|string|null>  $ids
     * @return Collection<int, int>
     */
    protected function normalizeIds(iterable $ids): Collection
    {
        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $taxonIds
     * @return Collection<int, int>
     */
    protected function taxonIdsWithDescendants(Collection $taxonIds): Collection
    {
        $allIds = $taxonIds->values();
        $frontier = $taxonIds;

        while ($frontier->isNotEmpty()) {
            $children = Taxon::query()
                ->whereIn('parent_id', $frontier->all())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->diff($allIds)
                ->values();

            $allIds = $allIds->merge($children)->unique()->values();
            $frontier = $children;
        }

        return $allIds;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  Collection<int, int>  $taxonIds
     * @return Collection<int, int>
     */
    protected function modelTaxonIdsFor(string $modelClass, Collection $taxonIds): Collection
    {
        return DB::table('model_taxons')
            ->whereIn('taxon_id', $taxonIds->all())
            ->whereIn('model_type', $this->morphTypesFor($modelClass))
            ->pluck('model_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    protected function morphTypesFor(string $modelClass): array
    {
        $model = new $modelClass;

        return array_values(array_unique([
            $modelClass,
            $model->getMorphClass(),
        ]));
    }

    /**
     * @return class-string<Model>|null
     */
    protected function classForMorphType(string $morphType): ?string
    {
        foreach ([Product::class, MasterProduct::class, GroupProduct::class, Post::class, Taxon::class] as $modelClass) {
            if (in_array($morphType, $this->morphTypesFor($modelClass), true)) {
                return $modelClass;
            }
        }

        return class_exists($morphType) ? $morphType : null;
    }
}
