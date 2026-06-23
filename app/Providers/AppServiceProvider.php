<?php

namespace App\Providers;

use App\Contracts\CatalogSearchGateway;
use App\Enums\OrderStatus;
use App\Jobs\SendOrderEmailsJob;
use App\Listeners\UpdateSalesFigures;
use App\Models\MasterProduct;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Post;
use App\Models\Product;
use App\Models\Taxon;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Services\ElasticCatalogSearchGateway;
use App\Services\SearchIndexInvalidator;
use App\Support\ApiLocale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Vanilo\Category\Contracts\Taxon as TaxonContract;
use Vanilo\MasterProduct\Contracts\MasterProduct as MasterProductContract;
use Vanilo\Order\Events\OrderWasCreated;
use Vanilo\Order\Models\OrderProxy;
use Vanilo\Product\Contracts\Product as ProductContract;
use Vanilo\Translation\Models\Translation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \Vanilo\Foundation\Listeners\UpdateSalesFigures::class,
            UpdateSalesFigures::class,
        );

        $this->app->bind(
            CatalogSearchGateway::class,
            ElasticCatalogSearchGateway::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        concord()->registerEnum(\Vanilo\Order\Contracts\OrderStatus::class, OrderStatus::class);
        $this->configureDefaults();
        $this->configureSearchIndexInvalidation();

        Event::listen(OrderWasCreated::class, function (OrderWasCreated $event) {
            SendOrderEmailsJob::dispatch($event->getOrder(), 'placed', null, ApiLocale::current());
        });

        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        Vite::macro('image', function ($asset) {
            return $this->asset("resources/images/{$asset}");
        });

        Vite::macro('audio', function ($asset) {
            return $this->asset("resources/audio/{$asset}");
        });

        $this->app->concord->registerModel(
            ProductContract::class,
            Product::class,
            TaxonContract::class,
            Taxon::class
        );

        $this->app->concord->registerModel(
            \Vanilo\Order\Contracts\Order::class,
            Order::class
        );

        $this->app->concord->registerModel(
            \Vanilo\Order\Contracts\OrderItem::class,
            OrderItem::class
        );

        $this->app->concord->registerModel(
            MasterProductContract::class,
            MasterProduct::class
        );

        OrderProxy::observe(OrderObserver::class);

        config([
            'vanilo.order.number.sequential_number' => [
                'start_sequence_from' => 1,
                'prefix' => 'PO-',
                'pad_length' => 4,
                'pad_string' => '0',
            ],
        ]);
    }

    protected function configureSearchIndexInvalidation(): void
    {
        $deletedProductDependencies = [];
        $deletedMaterialDependencies = [];
        $deletedPrinterDependencies = [];
        $deletedTaxonDependencies = [];

        Product::saved(function (Product $product): void {
            app(SearchIndexInvalidator::class)->reindexForProduct(
                $product,
                $product->wasChanged('material_id') ? (int) $product->getOriginal('material_id') : null
            );
        });

        Product::deleting(function (Product $product) use (&$deletedProductDependencies): void {
            $deletedProductDependencies[$product->getKey()] = [
                'material_id' => $product->material_id,
                'printer_ids' => $product->printers()->pluck('posts.id')->all(),
            ];
        });

        Product::deleted(function (Product $product) use (&$deletedProductDependencies): void {
            $dependencies = $deletedProductDependencies[$product->getKey()] ?? null;
            unset($deletedProductDependencies[$product->getKey()]);

            if ($dependencies === null) {
                return;
            }

            $invalidator = app(SearchIndexInvalidator::class);
            $invalidator->reindexMaterials([$dependencies['material_id']]);
            $invalidator->reindexPrinters($dependencies['printer_ids']);
        });

        Material::saved(function (Material $material): void {
            app(SearchIndexInvalidator::class)->reindexForMaterial($material);
        });

        Material::deleting(function (Material $material) use (&$deletedMaterialDependencies): void {
            $deletedMaterialDependencies[$material->getKey()] = [
                'product_ids' => $material->products()->pluck('id')->all(),
                'master_product_ids' => $material->masterProducts()->pluck('id')->all(),
            ];
        });

        Material::deleted(function (Material $material) use (&$deletedMaterialDependencies): void {
            $dependencies = $deletedMaterialDependencies[$material->getKey()] ?? null;
            unset($deletedMaterialDependencies[$material->getKey()]);

            if ($dependencies === null) {
                return;
            }

            $invalidator = app(SearchIndexInvalidator::class);
            $invalidator->reindexProducts($dependencies['product_ids']);
            $invalidator->reindexMasterProducts($dependencies['master_product_ids']);
        });

        Post::saved(function (Post $post): void {
            if ($post->post_type === 'printer') {
                app(SearchIndexInvalidator::class)->reindexForPrinter($post);
            }
        });

        Post::deleting(function (Post $post) use (&$deletedPrinterDependencies): void {
            if ($post->post_type === 'printer') {
                $deletedPrinterDependencies[$post->getKey()] = $post->products()->pluck('products.id')->all();
            }
        });

        Post::deleted(function (Post $post) use (&$deletedPrinterDependencies): void {
            if ($post->post_type === 'printer') {
                app(SearchIndexInvalidator::class)->reindexProducts($deletedPrinterDependencies[$post->getKey()] ?? []);
                unset($deletedPrinterDependencies[$post->getKey()]);
            }
        });

        Taxon::saved(function (Taxon $taxon): void {
            app(SearchIndexInvalidator::class)->reindexForTaxons([$taxon->getKey()]);
        });

        Taxon::deleting(function (Taxon $taxon) use (&$deletedTaxonDependencies): void {
            $rows = DB::table('model_taxons')
                ->where('taxon_id', $taxon->getKey())
                ->get(['model_type', 'model_id']);

            $deletedTaxonDependencies[$taxon->getKey()] = [
                'product_ids' => $rows->whereIn('model_type', [morph_type_of(Product::class), Product::class])->pluck('model_id')->all(),
                'master_product_ids' => $rows->whereIn('model_type', [morph_type_of(MasterProduct::class), MasterProduct::class])->pluck('model_id')->all(),
                'material_ids' => $rows->whereIn('model_type', [morph_type_of(Material::class), Material::class])->pluck('model_id')->all(),
            ];
        });

        Taxon::deleted(function (Taxon $taxon) use (&$deletedTaxonDependencies): void {
            $dependencies = $deletedTaxonDependencies[$taxon->getKey()] ?? null;
            unset($deletedTaxonDependencies[$taxon->getKey()]);

            if ($dependencies === null) {
                return;
            }

            app(SearchIndexInvalidator::class)->reindexTaxonAssignmentTargets(
                $dependencies['product_ids'],
                $dependencies['master_product_ids'],
                $dependencies['material_ids'],
            );
        });

        Translation::saved(function (Translation $translation): void {
            app(SearchIndexInvalidator::class)->reindexForTranslation($translation);
        });

        Translation::deleted(function (Translation $translation): void {
            app(SearchIndexInvalidator::class)->reindexForTranslation($translation);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        $this->app->concord->registerModel(\Konekt\User\Contracts\User::class, User::class);

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
