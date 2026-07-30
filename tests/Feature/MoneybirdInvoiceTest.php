<?php

use App\Exceptions\MoneybirdException;
use App\Livewire\OrderTable;
use App\Models\MoneybirdSetting;
use App\Models\Order;
use App\Models\Product;
use App\Services\Moneybird\MoneybirdClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Konekt\Address\Models\Address;
use Konekt\Address\Models\Country;
use Livewire\Livewire;
use Vanilo\Order\Models\Billpayer;
use Vanilo\Order\Models\OrderItemProxy;
use Vanilo\Order\Models\OrderProxy;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('i', 32)));
    Config::set('cache.default', 'array');
    Config::set('services.zeker_gemak_moneybird', [
        'client_id' => 'zeker-client',
        'client_secret' => 'zeker-secret',
        'redirect_uri' => 'https://zeker-gemak.test/moneybird/callback',
        'api_url' => 'https://moneybird.test/api/v2',
        'authorize_url' => 'https://moneybird.test/oauth/authorize',
        'token_url' => 'https://moneybird.test/oauth/token',
        'dashboard_url' => 'https://moneybird.test',
        'connect_timeout' => 1,
        'timeout' => 2,
        'scopes' => ['sales_invoices', 'settings'],
    ]);
    Country::create([
        'id' => 'NL',
        'name' => 'Netherlands',
        'phonecode' => 31,
        'is_eu_member' => true,
    ]);
    MoneybirdSetting::create([
        'configuration' => [
            ...MoneybirdSetting::resolved(),
            'connected' => true,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour()->timestamp,
            'administration_id' => 'administration-1',
            'workflow_id' => 'workflow-1',
            'document_style_id' => 'style-1',
            'ledger_account_id' => 'ledger-1',
        ],
    ]);
});

function moneybird_invoice_order(): Order
{
    $address = Address::create([
        'type' => 'billing',
        'name' => 'Ada Lovelace',
        'firstname' => 'Ada',
        'lastname' => 'Lovelace',
        'country_id' => 'NL',
        'postalcode' => '1234 AB',
        'city' => 'Amsterdam',
        'address' => 'Main Street 12',
    ]);
    $billpayer = Billpayer::create([
        'company_name' => 'Analytical Engines BV',
        'firstname' => 'Ada',
        'lastname' => 'Lovelace',
        'email' => 'ada@example.test',
        'phone' => '+31612345678',
        'address_id' => $address->id,
    ]);
    $product = Product::create([
        'name' => 'Door handle',
        'title' => 'Door handle',
        'slug' => 'door-handle',
        'sku' => 'DH-001',
        'price' => 10,
        'state' => 'active',
    ]);
    $orderClass = OrderProxy::modelClass();
    $order = $orderClass::create([
        'number' => 'ZG-1001',
        'status' => 'processing',
        'currency' => 'EUR',
        'billpayer_id' => $billpayer->id,
        'original_checkout_payload' => [
            'calculated_amounts' => [
                'lines' => [[
                    'unit_total' => '12.10',
                    'line_total' => '24.20',
                ]],
                'subtotal_total' => '24.20',
                'discount_total' => '2.42',
                'shipping_total' => '4.95',
                'fees_total' => '1.00',
                'total_tax' => '4.34',
                'grand_total' => '27.73',
            ],
        ],
    ]);
    $itemClass = OrderItemProxy::modelClass();
    $itemClass::create([
        'order_id' => $order->id,
        'product_type' => 'product',
        'product_id' => $product->id,
        'name' => $product->name,
        'quantity' => 2,
        'price' => 10,
    ]);

    return $order->refresh();
}

function fake_moneybird_invoice_api(int $invoiceStatus = 201): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://moneybird.test/api/v2/administration-1/contacts/filter.json*' => Http::response([]),
        'https://moneybird.test/api/v2/administration-1/contacts.json' => Http::response(['id' => 'contact-1'], 201),
        'https://moneybird.test/api/v2/administration-1/sales_invoices.json' => Http::response(
            $invoiceStatus === 201 ? [
                'id' => 'invoice-1',
                'invoice_id' => '2026-0001',
                'state' => 'draft',
                'url' => 'https://moneybird.test/invoices/invoice-1',
            ] : ['error' => 'unavailable'],
            $invoiceStatus,
        ),
    ]);
}

test('creates an invoice from final backend totals and customer details', function () {
    fake_moneybird_invoice_api();
    $order = moneybird_invoice_order();

    app(MoneybirdClient::class)->createInvoice($order);

    expect($order->refresh())
        ->moneybird_invoice_id->toBe('invoice-1')
        ->moneybird_invoice_number->toBe('2026-0001')
        ->moneybird_invoice_status->toBe('draft')
        ->moneybird_invoice_url->toBe('https://moneybird.test/invoices/invoice-1');

    Http::assertSent(function ($request): bool {
        if (! str_ends_with($request->url(), '/sales_invoices.json')) {
            return false;
        }

        $invoice = $request['sales_invoice'];
        $total = collect($invoice['details_attributes'])->sum(
            fn (array $line): float => (float) $line['amount'] * (float) $line['price'],
        );

        return round($total, 2) === 27.73
            && $invoice['reference'] === 'ZG-1001'
            && $invoice['workflow_id'] === 'workflow-1'
            && $invoice['document_style_id'] === 'style-1'
            && collect($invoice['details_attributes'])->every(
                fn (array $line): bool => $line['ledger_account_id'] === 'ledger-1',
            );
    });
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/contacts.json')
        && $request['contact']['email'] === 'ada@example.test'
        && $request['contact']['company_name'] === 'Analytical Engines BV');
});

test('does not send duplicate invoice requests', function () {
    fake_moneybird_invoice_api();
    $order = moneybird_invoice_order();
    $client = app(MoneybirdClient::class);

    $client->createInvoice($order);
    $client->createInvoice($order->refresh());

    Http::assertSentCount(3);
    expect($order->refresh()->moneybird_invoice_id)->toBe('invoice-1');
});

test('failed Moneybird requests leave the order uninvoiced', function () {
    fake_moneybird_invoice_api(503);
    $order = moneybird_invoice_order();

    expect(fn () => app(MoneybirdClient::class)->createInvoice($order))
        ->toThrow(MoneybirdException::class, 'Moneybird could not create the invoice.');
    expect($order->refresh())
        ->moneybird_invoice_id->toBeNull()
        ->moneybird_invoice_status->toBeNull();
});

test('downloads the stored Moneybird invoice as a PDF', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://moneybird.test/api/v2/administration-1/sales_invoices/invoice-1/download_pdf.json' => Http::response(
            '%PDF-1.4 Zeker-Gemak invoice',
            headers: ['Content-Type' => 'application/pdf'],
        ),
    ]);
    $order = moneybird_invoice_order();
    $order->update(['moneybird_invoice_id' => 'invoice-1']);

    Livewire::test(OrderTable::class)
        ->call('downloadMoneybirdInvoice', [$order->id])
        ->assertFileDownloaded(
            'moneybird-invoice-ZG-1001.pdf',
            '%PDF-1.4 Zeker-Gemak invoice',
            'application/pdf',
        );

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer access-token'));
});

test('shows row-specific invoice loading and download actions', function () {
    $order = moneybird_invoice_order();
    $actions = collect(app(OrderTable::class)->actions($order));
    $create = $actions->firstWhere('action', 'create-moneybird-invoice');
    $loading = $actions->firstWhere('action', 'creating-moneybird-invoice');

    expect($create->slot)->toBe('Create invoice')
        ->and($create->attributes)->toMatchArray([
            'wire:loading.remove' => '',
            'wire:loading.attr' => 'disabled',
            'wire:target' => "createMoneybirdInvoice({$order->id})",
        ])
        ->and($loading->slot)->toBe('Creating invoice...')
        ->and($loading->attributes['wire:target'])->toBe("createMoneybirdInvoice({$order->id})");

    $order->update([
        'moneybird_invoice_id' => 'invoice-1',
        'moneybird_invoice_status' => 'draft',
    ]);
    $actions = collect(app(OrderTable::class)->actions($order->refresh()));

    expect($actions->firstWhere('action', 'download-moneybird-invoice')->slot)
        ->toBe('Download invoice')
        ->and($actions->contains('action', 'view-moneybird-invoice'))->toBeFalse();
});
