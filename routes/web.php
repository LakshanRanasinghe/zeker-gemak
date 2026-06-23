<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\WysiwygUploadController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;
use Vanilo\Order\Models\Order;
use Vanilo\Order\Models\OrderProxy;
use Vanilo\Properties\Models\PropertyValue;
use Vanilo\Translation\Models\Translation;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'verified', 'web', RoleMiddleware::using('admin')])->group(function () {
    Route::get('lang/{locale}', function ($locale) {
        if (array_key_exists($locale, config('app.available_locales'))) {
            session(['locale' => $locale]);
        }

        return redirect()->back();
    })->name('lang.switch');

    Route::livewire('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('products', 'products.index')->name('products.index');
    Route::livewire('products/create', 'products.create-update')->name('products.create');
    Route::livewire('products/{productKey}/edit', 'products.create-update')->name('products.edit');

    Route::livewire('group-products', 'group-products.index')->name('group-products.index');
    Route::livewire('group-products/create', 'group-products.create-update')->name('group-products.create');
    Route::livewire('group-products/{groupProduct}/edit', 'group-products.create-update')->name('group-products.edit');

    Route::livewire('pages', 'pages.index')->name('pages.index');
    Route::livewire('pages/create', 'pages.create-update')->name('pages.create');
    Route::livewire('pages/{page}/edit', 'pages.create-update')->name('pages.edit');

    Route::livewire('posts', 'posts.index')->name('posts.index');
    Route::livewire('posts/create', 'posts.create-update')->name('posts.create');
    Route::livewire('posts/{post}/edit', 'posts.create-update')->name('posts.edit');

    Route::livewire('faq-items', 'faq-items.index')->name('faq-items.index');
    Route::livewire('faq-items/create', 'faq-items.create-update')->name('faq-items.create');
    Route::livewire('faq-items/{faqItem}/edit', 'faq-items.create-update')->name('faq-items.edit');

    Route::livewire('faq-pages', 'faq-pages.index')->name('faq-pages.index');
    Route::livewire('faq-pages/create', 'faq-pages.create-update')->name('faq-pages.create');
    Route::livewire('faq-pages/{faqPage}/edit', 'faq-pages.create-update')->name('faq-pages.edit');

    Route::livewire('printers', 'printers.index')->name('printers.index');
    Route::livewire('printers/create', 'printers.create-update')->name('printers.create');
    Route::livewire('printers/{printer}/edit', 'printers.create-update')->name('printers.edit');

    Route::livewire('materials', 'materials.index')->name('materials.index');
    Route::livewire('materials/create', 'materials.create-update')->name('materials.create');
    Route::livewire('materials/{material}/edit', 'materials.create-update')->name('materials.edit');

    Route::livewire('taxonomies', 'taxonomies.index')->name('taxonomies.index');
    Route::livewire('taxonomies/create', 'taxonomies.create-update')->name('taxonomies.create');
    Route::livewire('taxonomies/{taxonomy}/edit', 'taxonomies.create-update')->name('taxonomies.edit');

    Route::controller(WysiwygUploadController::class)->prefix('wysiwyg')->name('wysiwyg.')->group(function () {
        Route::post('upload', 'upload')->name('upload');
        Route::delete('{wysiwygMedia}', 'destroy')->name('destroy');
    });

    Route::livewire('orders', 'orders.index')->name('orders.index');
    Route::livewire('orders/create', 'orders.create-update')->name('orders.create');
    Route::livewire('orders/{order}/edit', 'orders.create-update')->name('orders.edit');

    Route::livewire('customers', 'customers.index')->name('customers.index');
    Route::livewire('customers/create', 'customers.create-update')->name('customers.create');
    Route::livewire('customers/{id}/edit', 'customers.create-update')->name('customers.edit');

    Route::livewire('coupons', 'coupons.index')->name('coupons.index');
    Route::livewire('coupons/create', 'coupons.create-update')->name('coupons.create');
    Route::livewire('coupons/{shopCoupon}/edit', 'coupons.create-update')->name('coupons.edit');

    // Route::livewire('customer-reviews', 'customer-reviews.index')->name('customer-reviews.index');
    // Route::livewire('customer-reviews/create', 'customer-reviews.create-update')->name('customer-reviews.create');
    // Route::livewire('customer-reviews/{customerReview}/edit', 'customer-reviews.create-update')->name('customer-reviews.edit');

    Route::livewire('discount-groups', 'discount-group.index')->name('discount-groups.index');
    Route::livewire('discount-groups/create', 'discount-group.create-update')->name('discount-groups.create');
    Route::livewire('discount-groups/{discountGroup}/edit', 'discount-group.create-update')->name('discount-groups.edit');

    Route::livewire('warranty-groups', 'warranty-groups.index')->name('warranty-groups.index');
    Route::livewire('warranty-groups/create', 'warranty-groups.create-update')->name('warranty-groups.create');
    Route::livewire('warranty-groups/{warrantyGroup}/edit', 'warranty-groups.create-update')->name('warranty-groups.edit');

    // Settings
    Route::livewire('zones', 'zones.index')->name('zones.index');
    Route::livewire('zones/create', 'zones.create-update')->name('zones.create');
    Route::livewire('zones/{zone}/edit', 'zones.create-update')->name('zones.edit');
    Route::livewire('tax-settings', 'tax-settings.index')->name('tax-settings.index');
    Route::livewire('tax-settings/create', 'tax-settings.create-update')->name('tax-settings.create');
    Route::livewire('tax-settings/{taxRate}/edit', 'tax-settings.create-update')->name('tax-settings.edit');
    Route::livewire('shipping-settings', 'shipping-settings.index')->name('shipping-settings.index');
    Route::livewire('shipping-settings/create', 'shipping-settings.create-update')->name('shipping-settings.create');
    Route::livewire('shipping-settings/{shippingMethod}/edit', 'shipping-settings.create-update')->name('shipping-settings.edit');
    Route::livewire('admin-settings', 'settings.index')->name('settings.index');
    Route::livewire('ai-settings', 'ai-settings.index')->name('ai-settings.index');
    Route::livewire('availability', 'availability.index')->name('availability.index');

    Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');

    Route::get('/test', function () {

        // $order = Order::find(5);
        // // $translation = Translation::findByModel($product, 'en');

        // echo '<pre>';
        // print_r($order->user);
        // echo '</pre>';
        // $propertyValue = PropertyValue::findByPropertyAndValue('materiaal-code', 'DIA055');

        // if (! $propertyValue) {
        //     return 'Property value not found';
        // }

        // $products = Product::whereHas('propertyValues', function ($query) use ($propertyValue) {
        //     $query->where('property_values.id', $propertyValue->id);
        // })->get();

        // echo '<h1>Products with materiaal-code: DIA055</h1>';
        // echo '<ul>';
        // foreach ($products as $product) {
        //     echo '<li>'.$product->name.' (SKU: '.$product->sku.')</li>';
        // }
        // echo '</ul>';
        // echo '<p>Total found: '.$products->count().'</p>';
    });

    // Email Previews
    Route::prefix('emails/preview')->group(function () {
        Route::get('/', function () {
            $routes = collect(Route::getRoutes())->filter(function ($route) {
                return str_contains($route->getName(), 'emails.preview.');
            });

            echo '<div style="font-family: sans-serif; padding: 40px; max-width: 600px; margin: 0 auto; background: #fff7ed; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">';
            echo '<h1 style="color: #ea580c; margin-bottom: 24px;">Email Template Previews</h1>';
            echo '<ul style="list-style: none; padding: 0;">';
            foreach ($routes as $route) {
                $name = str_replace('emails.preview.', '', $route->getName());
                $name = ucwords(str_replace('-', ' ', $name));
                echo '<li style="margin-bottom: 12px;">';
                echo '<a href="/'.$route->uri().'" style="display: block; padding: 16px 20px; background: #fff; color: #ea580c; text-decoration: none; border-radius: 12px; font-weight: 600; border: 1px solid #fed7aa; transition: all 0.2s;">';
                echo '&#128231; '.$name;
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        });

        $dummyOrder = fn () => OrderProxy::first() ?? new (OrderProxy::modelClass())([
            'number' => 'BL-2024-001',
            'status' => 'processing',
        ]);

        Route::get('order-placed-customer', function () use ($dummyOrder) {
            return view('emails.order-placed-customer', ['order' => $dummyOrder()]);
        })->name('emails.preview.order-placed-customer');

        Route::get('order-placed-admin', function () use ($dummyOrder) {
            return view('emails.order-placed-admin', ['order' => $dummyOrder()]);
        })->name('emails.preview.order-placed-admin');

        Route::get('order-shipped-customer', function () use ($dummyOrder) {
            return view('emails.order-shipped-customer', ['order' => $dummyOrder()]);
        })->name('emails.preview.order-shipped-customer');

        Route::get('order-cancelled-customer', function () use ($dummyOrder) {
            return view('emails.order-cancelled-customer', ['order' => $dummyOrder()]);
        })->name('emails.preview.order-cancelled-customer');

        Route::get('order-cancelled-admin', function () use ($dummyOrder) {
            return view('emails.order-cancelled-admin', ['order' => $dummyOrder()]);
        })->name('emails.preview.order-cancelled-admin');

        Route::get('order-status-updated-customer', function () use ($dummyOrder) {
            return view('emails.order-status-updated-customer', [
                'order' => $dummyOrder(),
                'oldStatus' => 'pending',
            ]);
        })->name('emails.preview.order-status-updated-customer');

        Route::get('callback-request-admin', function () {
            return view('emails.callback-request-admin', [
                'data' => [
                    'full_phone_number' => '+31 6 12345678',
                    'country' => 'Netherlands',
                    'country_code' => 'NL',
                    'dial_code' => '+31',
                    'phone_number' => '612345678',
                ],
            ]);
        })->name('emails.preview.callback-request-admin');

        Route::get('contact-form-admin', function () {
            return view('emails.contact-form-admin', [
                'data' => [
                    'email' => 'john.doe@example.com',
                    'message' => "Hello, I am interested in your custom labels for my business. Could you please provide more information about bulk pricing?\n\nKind regards,\nJohn Doe",
                ],
            ]);
        })->name('emails.preview.contact-form-admin');

        Route::get('reset-password', function () {
            return view('emails.reset-password', [
                'user' => (object) ['name' => 'John Doe'],
                'resetUrl' => 'http://businesslabels.test/password/reset/dummy-token',
                'logoUrl' => asset('images/bbnl-logo.png'),
                'appUrl' => config('app.url'),
            ]);
        })->name('emails.preview.reset-password');
    });
});

require __DIR__.'/settings.php';
