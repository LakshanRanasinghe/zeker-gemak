<?php

use App\Exceptions\ShippingDocumentException;
use App\Jobs\SendOrderEmailsJob;
use App\Livewire\OrderTable;
use App\Mail\OrderShippedCustomer;
use App\Services\DhlClient;
use App\Services\GenerateDhlShippingDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Konekt\Address\Models\Address;
use Konekt\Address\Models\Country;
use Livewire\Livewire;
use Vanilo\Order\Models\Billpayer;
use Vanilo\Order\Models\OrderProxy;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('queue.connections.database.after_commit', false);

    Country::create([
        'id' => 'NL',
        'name' => 'Netherlands',
        'phonecode' => 31,
        'is_eu_member' => true,
    ]);

    Config::set('services.zeker_gemak_dhl', [
        'base_url' => 'https://dhl.test/',
        'user_id' => 'dhl-user',
        'key' => 'dhl-key',
        'account_id' => '12345678',
        'product' => 'DFY-B2C',
        'parcel_type' => 'SMALL',
        'connect_timeout' => 1,
        'timeout' => 2,
        'sender' => [
            'company' => 'Zeker Gemak',
            'first_name' => 'Zeker',
            'last_name' => 'Gemak',
            'street' => 'Afzenderstraat',
            'house_number' => '10',
            'house_number_addition' => 'A',
            'postal_code' => '1234 AB',
            'city' => 'Amsterdam',
            'country_code' => 'NL',
            'email' => 'shipping@zeker-gemak.test',
            'phone' => '+31201234567',
            'vat_number' => 'NL123456789B01',
            'eori_number' => 'NL123456789',
        ],
    ]);
    Config::set('services.dropbox', [
        'api_url' => 'https://dropbox-api.test',
        'content_url' => 'https://dropbox-content.test',
        'authorization_token' => 'dropbox-token',
        'connect_timeout' => 1,
        'timeout' => 2,
    ]);
});

function dhl_shipping_order(string $status = 'processing')
{
    $billingAddress = Address::create([
        'type' => 'billing',
        'name' => 'Jane Doe',
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'country_id' => 'NL',
        'postalcode' => '1011 AB',
        'city' => 'Amsterdam',
        'address' => 'Keizersgracht 1',
    ]);
    $shippingAddress = Address::create([
        'type' => 'shipping',
        'name' => 'Jane Doe',
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'country_id' => 'NL',
        'postalcode' => '1012 JS',
        'city' => 'Amsterdam',
        'address' => 'Damrak 12 A',
        'phone' => '+31612345678',
    ]);
    $billpayer = Billpayer::create([
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'email' => 'jane@example.com',
        'address_id' => $billingAddress->id,
    ]);
    $orderClass = OrderProxy::modelClass();

    return $orderClass::create([
        'number' => 'ZG-1001',
        'status' => $status,
        'billpayer_id' => $billpayer->id,
        'shipping_address_id' => $shippingAddress->id,
    ]);
}

function fakeSuccessfulShippingApis(?string $failedDropboxPath = null): void
{
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($failedDropboxPath) {
        if ($request->url() === 'https://api.dropbox.test/oauth2/token') {
            return Http::response(['access_token' => 'fresh-dropbox-token']);
        }

        if ($request->url() === 'https://dhl.test/authenticate/api-key') {
            return Http::response(['accessToken' => 'access-token']);
        }

        if ($request->url() === 'https://dhl.test/shipments') {
            return Http::response([
                'shipmentId' => 'shipment-1',
                'pieces' => [[
                    'labelId' => 'label-1',
                    'trackerCode' => 'JVGL123456789',
                ]],
            ]);
        }

        if ($request->url() === 'https://dhl.test/labels/label-1') {
            return Http::response(['pdf' => base64_encode('%PDF DHL label')]);
        }

        if ($request->url() === 'https://dropbox-api.test/2/files/create_folder_v2') {
            return Http::response(['metadata' => ['path_display' => $request['path']]]);
        }

        if ($request->url() === 'https://dropbox-content.test/2/files/upload') {
            $arguments = json_decode($request->header('Dropbox-API-Arg')[0], true, flags: JSON_THROW_ON_ERROR);

            return $arguments['path'] === $failedDropboxPath
                ? Http::response(['error_summary' => 'path/upload_failed'], 500)
                : Http::response(['path_display' => $arguments['path']]);
        }

        return Http::response([], 404);
    });
}

function dhl_shipment_options(): array
{
    return [
        'recipient' => [
            'first_name' => 'Jan',
            'last_name' => 'Jansen',
            'company' => 'Jansen BV',
            'is_business' => true,
            'email' => 'jan@example.com',
            'phone' => '+31687654321',
            'street' => 'Nieuweweg',
            'house_number' => '44',
            'addition' => 'B',
            'postal_code' => '1234 AB',
            'city' => 'Utrecht',
            'country_code' => 'NL',
        ],
        'carrier' => 'DHL-PARCEL',
        'parcel_type' => 'MEDIUM',
        'shipping_method' => 'EPL',
    ];
}

it('uploads both documents before shipping the order and queues the shipped email', function () {
    Queue::fake();
    fakeSuccessfulShippingApis();
    $order = dhl_shipping_order();

    $result = app(GenerateDhlShippingDocuments::class)->handle($order, dhl_shipment_options());

    expect($order->refresh())
        ->status->value()->toBe('shipped')
        ->tracking_number->toBe('JVGL123456789')
        ->and($order->dhl_data)->toMatchArray([
            'shipment_id' => 'shipment-1',
            'label_id' => 'label-1',
            'packing_slip_path' => '/Slips Arthur/Pakbon-ZG-1001.pdf',
            'label_path' => '/Labels Arthur/DHL-label-ZG-1001.pdf',
            'shipment' => [
                'carrier' => 'DHL-PARCEL',
                'parcel_type' => 'MEDIUM',
                'shipping_method' => 'EPL',
            ],
        ])
        ->and($result['packing_slip_path'])->toBe('/Slips Arthur/Pakbon-ZG-1001.pdf')
        ->and($result['label_path'])->toBe('/Labels Arthur/DHL-label-ZG-1001.pdf');

    Queue::assertPushed(SendOrderEmailsJob::class, fn (SendOrderEmailsJob $job) => $job->type === 'shipped' && $job->order->is($order));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://dhl.test/shipments'
        && $request['accountId'] === '12345678'
        && $request['shipper']['name']['companyName'] === 'Zeker Gemak'
        && $request['product'] === 'EPL'
        && $request['receiver']['address']['isBusiness'] === true
        && $request['receiver']['name']['companyName'] === 'Jansen BV'
        && $request['receiver']['address']['street'] === 'Nieuweweg'
        && $request['receiver']['address']['number'] === '44'
        && $request['pieces'][0]['parcelType'] === 'MEDIUM'
        && $request['pieces'][0]['weight'] === 1
        && $request['pieces'][0]['dimensions'] === ['length' => 10, 'width' => 10, 'height' => 10]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://dropbox-api.test/2/files/create_folder_v2'
        && in_array($request['path'], ['/Slips Arthur', '/Labels Arthur'], true));
});

it('refreshes the Dropbox token once and reuses it for all document uploads', function () {
    Queue::fake();
    Config::set('services.dropbox.oauth_url', 'https://api.dropbox.test/oauth2/token');
    Config::set('services.dropbox.app_key', 'dropbox-key');
    Config::set('services.dropbox.app_secret', 'dropbox-secret');
    Config::set('services.dropbox.refresh_token', 'dropbox-refresh-token');
    Config::set('services.dropbox.authorization_token', 'expired-token');
    fakeSuccessfulShippingApis();
    $order = dhl_shipping_order();

    app(GenerateDhlShippingDocuments::class)->handle($order, dhl_shipment_options());

    $tokenRequests = collect(Http::recorded())
        ->filter(fn (array $record) => $record[0]->url() === 'https://api.dropbox.test/oauth2/token');

    expect($tokenRequests)->toHaveCount(1);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://dropbox-')
        && $request->hasHeader('Authorization', 'Bearer fresh-dropbox-token'));
});

it('downloads a generated shipping label from Dropbox', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://dropbox-content.test/2/files/download' => Http::response('%PDF saved DHL label'),
    ]);
    $order = dhl_shipping_order();
    $order->forceFill([
        'dhl_data' => ['label_path' => '/Labels Arthur/DHL-label-ZG-1001.pdf'],
    ])->saveQuietly();

    Livewire::test(OrderTable::class)
        ->call('downloadShippingLabel', [$order->id])
        ->assertFileDownloaded(
            'DHL-label-ZG-1001.pdf',
            '%PDF saved DHL label',
            'application/pdf',
        );

    Http::assertSent(function (Request $request): bool {
        $arguments = json_decode($request->header('Dropbox-API-Arg')[0], true, flags: JSON_THROW_ON_ERROR);

        return $request->url() === 'https://dropbox-content.test/2/files/download'
            && $arguments['path'] === '/Labels Arthur/DHL-label-ZG-1001.pdf';
    });
});

it('does not call Dropbox when an order has no generated shipping label', function () {
    Http::preventStrayRequests();
    $order = dhl_shipping_order();

    Livewire::test(OrderTable::class)
        ->call('downloadShippingLabel', [$order->id])
        ->assertNoFileDownloaded();

    Http::assertNothingSent();
});

it('does not ship or email when the Dropbox token request times out', function () {
    Queue::fake();
    Config::set('services.dropbox.oauth_url', 'https://api.dropbox.test/oauth2/token');
    Config::set('services.dropbox.app_key', 'dropbox-key');
    Config::set('services.dropbox.app_secret', 'dropbox-secret');
    Config::set('services.dropbox.refresh_token', 'dropbox-refresh-token');
    Http::preventStrayRequests();
    Http::fake([
        'https://dhl.test/authenticate/api-key' => Http::response(['accessToken' => 'access-token']),
        'https://dhl.test/shipments' => Http::response([
            'shipmentId' => 'shipment-1',
            'pieces' => [['labelId' => 'label-1', 'trackerCode' => 'JVGL123456789']],
        ]),
        'https://dhl.test/labels/label-1' => Http::response(['pdf' => base64_encode('%PDF DHL label')]),
        'https://api.dropbox.test/oauth2/token' => Http::failedConnection(),
    ]);
    $order = dhl_shipping_order();

    expect(fn () => app(GenerateDhlShippingDocuments::class)->handle($order, dhl_shipment_options()))
        ->toThrow(ShippingDocumentException::class);

    expect($order->refresh())
        ->status->value()->toBe('processing')
        ->tracking_number->toBeNull()
        ->dhl_data->toBeNull();
    Queue::assertNotPushed(SendOrderEmailsJob::class);
});

it('prefills and resets the per-order shipping label modal', function () {
    $order = dhl_shipping_order();

    Livewire::test(OrderTable::class)
        ->call('openShippingLabelModal', [$order->id])
        ->assertSet('labelOrderId', $order->id)
        ->assertSet('labelRecipient.street', 'Damrak')
        ->assertSet('labelRecipient.house_number', '12')
        ->assertSet('labelRecipient.addition', 'A')
        ->assertSet('labelRecipient.is_business', false)
        ->assertSet('labelCarrier', 'DHL-PARCEL')
        ->set('labelRecipient.is_business', true)
        ->assertSet('labelShippingMethod', 'EPL')
        ->set('labelRecipient.is_business', false)
        ->assertSet('labelShippingMethod', 'DFY-B2C')
        ->set('labelCarrier', 'DHL-EXPRESS')
        ->call('openShippingLabelModal', $order->id)
        ->assertSet('labelCarrier', 'DHL-PARCEL');
});

it('validates label choices before making external requests', function () {
    Queue::fake();
    Http::preventStrayRequests();
    $order = dhl_shipping_order();

    Livewire::test(OrderTable::class)
        ->call('openShippingLabelModal', $order->id)
        ->set('labelRecipient.is_business', null)
        ->set('labelShippingMethod', '')
        ->call('generateShippingLabel')
        ->assertHasErrors([
            'labelShippingMethod' => 'required',
            'labelRecipient.is_business' => 'required',
        ]);

    Http::assertNothingSent();
    expect($order->refresh()->status->value())->toBe('processing');
    Queue::assertNotPushed(SendOrderEmailsJob::class);
});

it('keeps a company visible without marking a consumer shipment as business', function () {
    fakeSuccessfulShippingApis();
    $order = dhl_shipping_order();
    $shipment = dhl_shipment_options();
    $shipment['recipient']['is_business'] = false;
    $shipment['shipping_method'] = 'DFY-B2C';

    app(DhlClient::class)->generateLabel($order, $shipment);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://dhl.test/shipments'
        && $request['product'] === 'DFY-B2C'
        && $request['receiver']['address']['isBusiness'] === false
        && $request['receiver']['name']['companyName'] === ''
        && $request['receiver']['address']['addition'] === 'B Jansen BV');
});

it('does not ship or email when DHL rejects label generation', function () {
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://dhl.test/authenticate/api-key' => Http::response(['accessToken' => 'access-token']),
        'https://dhl.test/shipments' => Http::response(['message' => 'Invalid shipment'], 422),
    ]);
    $order = dhl_shipping_order();

    expect(fn () => app(GenerateDhlShippingDocuments::class)->handle($order))
        ->toThrow(ShippingDocumentException::class);

    expect($order->refresh())
        ->status->value()->toBe('processing')
        ->tracking_number->toBeNull()
        ->dhl_data->toBeNull();
    Queue::assertNotPushed(SendOrderEmailsJob::class);
});

it('does not ship or email when the packing slip Dropbox upload fails', function () {
    Queue::fake();
    fakeSuccessfulShippingApis('/Slips Arthur/Pakbon-ZG-1001.pdf');
    $order = dhl_shipping_order();

    expect(fn () => app(GenerateDhlShippingDocuments::class)->handle($order))
        ->toThrow(ShippingDocumentException::class);

    expect($order->refresh()->status->value())->toBe('processing');
    Queue::assertNotPushed(SendOrderEmailsJob::class);
});

it('does not ship or email when the label Dropbox upload fails', function () {
    Queue::fake();
    fakeSuccessfulShippingApis('/Labels Arthur/DHL-label-ZG-1001.pdf');
    $order = dhl_shipping_order();

    expect(fn () => app(GenerateDhlShippingDocuments::class)->handle($order))
        ->toThrow(ShippingDocumentException::class);

    expect($order->refresh())
        ->status->value()->toBe('processing')
        ->tracking_number->toBeNull()
        ->dhl_data->toBeNull();
    Queue::assertNotPushed(SendOrderEmailsJob::class);
});

it('validates the DHL sender settings before calling either API', function () {
    Queue::fake();
    Http::preventStrayRequests();
    Config::set('services.zeker_gemak_dhl.sender.street');
    $order = dhl_shipping_order();

    expect(fn () => app(GenerateDhlShippingDocuments::class)->handle($order))
        ->toThrow(ShippingDocumentException::class);

    Http::assertNothingSent();
    expect($order->refresh()->status->value())->toBe('processing');
    Queue::assertNotPushed(SendOrderEmailsJob::class);
});

it('uses the Empire DHL tracking presentation in the Zeker Gemak shipped email', function () {
    $order = dhl_shipping_order('shipped');
    $order->forceFill(['tracking_number' => 'JVGL123456789'])->saveQuietly();

    $mail = new OrderShippedCustomer($order->fresh(['shippingAddress']));

    $mail->assertSeeInHtml('JVGL123456789')
        ->assertSeeInHtml('DHL')
        ->assertSeeInHtml('https://my.dhlecommerce.nl/home/tracktrace/JVGL123456789/1012JS')
        ->assertSeeInHtml('Zeker Gemak');
});
