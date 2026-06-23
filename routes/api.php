<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactsController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CustomerAddressController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerReviewController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\FavoritePrinterController;
use App\Http\Controllers\Api\FavoriteProductController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\GroupProductController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PrinterController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TeamMemberController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::apiResource('orders', OrderController::class)
        ->parameters(['orders' => 'orderId'])->names('orders');
    Route::get('users/{userId}/orders', [OrderController::class, 'userOrders'])->name('users.orders');

    Route::apiResource('customers', CustomerController::class)
        ->parameters(['customers' => 'customerId'])->names('customers');

    Route::get('/user/addresses', [CustomerAddressController::class, 'myAddresses']);
    Route::post('/user/addresses', [CustomerAddressController::class, 'storeOrUpdateMyAddress']);

    Route::apiResource('customers.addresses', CustomerAddressController::class)
        ->parameters(['customers' => 'customerId', 'addresses' => 'addressId'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::prefix('user/profile')->name('profile.')->controller(ProfileController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('/', 'update')->name('update');
        Route::put('password', 'updatePassword')->name('password');
    });

    Route::prefix('user/favorite-products')->name('favorite-products.')->controller(FavoriteProductController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('{type}/{id}', 'store')->whereIn('type', ['simple', 'variable'])->whereNumber('id')->name('store');
        Route::delete('{type}/{id}', 'destroy')->whereIn('type', ['simple', 'variable'])->whereNumber('id')->name('destroy');
        Route::get('{type}/{id}/check', 'check')->whereIn('type', ['simple', 'variable'])->whereNumber('id')->name('check');
    });

    Route::prefix('user/favorite-printers')->name('favorite-printers.')->controller(FavoritePrinterController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('{id}', 'store')->whereNumber('id')->name('store');
        Route::delete('{id}', 'destroy')->whereNumber('id')->name('destroy');
        Route::get('{id}/check', 'check')->whereNumber('id')->name('check');
    });
});

Route::post('login', [AuthController::class, 'login']);
Route::post('guest/orders', [OrderController::class, 'store'])->name('orders.guest.store');
Route::get('guest/orders/{number}', [OrderController::class, 'showByNumber'])->name('orders.guest.show');
Route::post('webhooks/mollie', [OrderController::class, 'webhook'])->name('webhooks.mollie');

Route::prefix('products')->name('products.')->controller(ProductController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{type}/slug/{slug}', 'showBySlug')->whereIn('type', ['simple', 'variable'])->name('show-by-slug');
    Route::get('{type}/{id}', 'show')->whereIn('type', ['simple', 'variable'])->whereNumber('id')->name('show');
    Route::post('/printer-products', 'getPrinterProducts')->name('printer-products');
    Route::post('/product-printers', 'getProductPrinters')->name('product-printers');
    Route::post('/material-products', 'getMaterialProducts')->name('material-products');
    Route::post('/compatibility', 'getCompatibility')->name('compatibility');
});

Route::prefix('group-products')->name('group-products.')->controller(GroupProductController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('slug/{slug}', 'showBySlug')->name('show-by-slug');
    Route::get('{id}', 'show')->whereNumber('id')->name('show');
});

Route::prefix('materials')->name('materials.')->controller(MaterialController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('slug/{slug}', 'showBySlug')->name('show-by-slug');
    Route::get('{id}/spec-sheet', 'downloadSpecSheet')->whereNumber('id')->name('spec-sheet');
    Route::get('{id}', 'show')->whereNumber('id')->name('show');
});

Route::prefix('printers')->name('printers.')->controller(PrinterController::class)->group(function () {
    Route::get('select', 'select')->name('select');
    Route::get('/', 'index')->name('index');
    Route::get('slug/{slug}', 'showBySlug')->name('show-by-slug');
    Route::get('{id}', 'show')->whereNumber('id')->name('show');
    Route::get('/search', [PrinterController::class, 'searchPrinter'])->name('search.printer');
});

Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/{slug}/products', [CategoryController::class, 'products'])->name('categories.products');
Route::get('/filters', [FilterController::class, 'index'])->name('filters.index');

Route::get('/coupons/{code}', [CouponController::class, 'show'])->name('coupons.show');

Route::prefix('reviews')->name('reviews.')->controller(CustomerReviewController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->middleware('throttle:5,1')->name('store');
});

Route::get('/search', [SearchController::class, 'search'])->name('search');

Route::prefix('pages')->name('pages.')->controller(PageController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/slug/{slug}', 'show')->name('show');
});

Route::prefix('posts')->name('posts.')->controller(PageController::class)->group(function () {
    Route::get('/', 'posts')->name('index');
    Route::get('/slug/{slug}', 'showPost')->name('show');
});

Route::prefix('faq')->name('faq.')->controller(FaqController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/slug/{slug}', 'show')->name('show');
});

Route::post('/drawer-booking', [ContactsController::class, 'drawerBooking'])->name('drawer-booking');
Route::post('/drawer-contact', [ContactsController::class, 'drawerContact'])->name('drawer-contact');
Route::post('/custom-made-request', [ContactsController::class, 'customMadeRequest'])->name('custom-made-request');
Route::post('/icc-profile-request', [ContactsController::class, 'iccProfileRequest'])->name('icc-profile-request');
Route::post('/request-printer', [ContactsController::class, 'requestPrinter'])->name('request-printer');
Route::post('/recycle-request', [ContactsController::class, 'recycleRequest'])->name('recycle-request');

Route::get('/availabilities', [AvailabilityController::class, 'index'])->name('availabilities.index');
Route::get('/team-members', [TeamMemberController::class, 'index'])->name('team-members.index');
Route::get('/popular-products', [\App\Http\Controllers\Api\PopularProductController::class, 'index'])->name('popular-products.index');

Route::post('/register', [UserController::class, 'register']);
Route::get('/register/data', [UserController::class, 'registerData']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/reset/password', [UserController::class, 'resetPassword']);

Route::prefix('account')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);

    Route::get('/orders', [UserController::class, 'getOrders']);

    Route::get('/addresses', [UserController::class, 'getAddresses']);
});
