<?php

namespace App\Providers;

use App\Contracts\CatalogSearchGateway;
use App\Enums\OrderStatus;
use App\Jobs\SendOrderEmailsJob;
use App\Listeners\UpdateSalesFigures;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Taxon;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Services\ElasticCatalogSearchGateway;
use App\Services\SearchIndexInvalidator;
use App\Support\ApiLocale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Konekt\Customer\Contracts\Customer as CustomerContract;
use Laravel\Scout\ModelObserver;
use Vanilo\Category\Contracts\Taxon as TaxonContract;
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
            SendOrderEmailsJob::dispatch($event->getOrder(), 'placed', null, ApiLocale::current())->afterCommit();
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

        config([
            'vanilo.order.number.sequential_number' => [
                'start_sequence_from' => 1,
                'prefix' => 'PO-',
                'pad_length' => 4,
                'pad_string' => '0',
            ],
        ]);

        $this->app->concord->registerModel(
            ProductContract::class,
            Product::class
        );

        $this->app->concord->registerModel(
            CustomerContract::class,
            Customer::class
        );

        $this->app->concord->registerModel(
            TaxonContract::class,
            Taxon::class
        );

        $this->app->concord->registerModel(
            \Vanilo\Order\Contracts\Order::class,
            Order::class
        );
        OrderProxy::observe(OrderObserver::class);

        $this->app->concord->registerModel(
            \Vanilo\Order\Contracts\OrderItem::class,
            OrderItem::class
        );

        Relation::morphMap([
            'product' => Product::class,
            'taxon' => Taxon::class,
            'order' => Order::class,
            'order_item' => OrderItem::class,
        ]);
    }

    protected function configureSearchIndexInvalidation(): void
    {
        $deletedTaxonDependencies = [];

        Taxon::saved(function (Taxon $taxon): void {
            if (ModelObserver::syncingDisabledFor(Product::class)) {
                return;
            }

            app(SearchIndexInvalidator::class)->reindexForTaxons([$taxon->getKey()]);
        });

        Taxon::deleting(function (Taxon $taxon) use (&$deletedTaxonDependencies): void {
            $rows = DB::table('model_taxons')
                ->where('taxon_id', $taxon->getKey())
                ->get(['model_type', 'model_id']);

            $deletedTaxonDependencies[$taxon->getKey()] = [
                'product_ids' => $rows->whereIn('model_type', [morph_type_of(Product::class), Product::class])->pluck('model_id')->all(),
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
