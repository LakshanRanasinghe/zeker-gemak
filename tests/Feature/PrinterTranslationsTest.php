<?php

use App\Jobs\SyncWooCommercePrintersJob;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Vanilo\Properties\Models\Property;
use Vanilo\Properties\Models\PropertyValue;
use Vanilo\Translation\Models\Translation;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.locale', 'nl');
    Config::set('app.main_locale', 'nl');
    Config::set('app.available_locales', [
        'en' => 'English',
        'nl' => 'Dutch',
    ]);
});

it('queues only the dutch printer sync because english is fetched from linked translations', function (): void {
    Queue::fake();

    Http::fake([
        'https://businesslabels.nl/wp-json/wp/v2/printers*' => Http::response([], 200),
    ]);

    artisan('app:sync-woocommerce-printers')
        ->assertSuccessful();

    Queue::assertPushed(SyncWooCommercePrintersJob::class, 1);
    Queue::assertPushed(SyncWooCommercePrintersJob::class, function (SyncWooCommercePrintersJob $job): bool {
        return $job->locale === 'nl'
            && $job->page === 1
            && $job->batch === 1;
    });
});

it('syncs dutch printers as primary records and stores linked english translations', function (): void {
    Http::fake(function ($request) {
        $data = $request->data();

        if (($data['include'] ?? null) === '85889') {
            return Http::response([
                printerPayload(
                    id: 85889,
                    title: 'English Printer',
                    slug: 'english-printer',
                    content: '<p>English content</p>',
                    subtitle: 'English subtitle',
                    translations: ['nl' => '85888', 'en' => '85889'],
                ),
            ], 200);
        }

        if (($data['lang'] ?? null) === 'nl') {
            return Http::response([
                printerPayload(
                    id: 85888,
                    title: 'Nederlandse Printer',
                    slug: 'nederlandse-printer',
                    content: '<p>Nederlandse inhoud</p>',
                    subtitle: 'Nederlandse ondertitel',
                    translations: ['nl' => '85888', 'en' => '85889'],
                ),
            ], 200);
        }

        return Http::response([], 200);
    });

    $job = new SyncWooCommercePrintersJob(page: 1, perPage: 10, batch: 1, locale: 'nl', skipMedia: true);
    $job->handle();

    $printer = Post::query()
        ->where('post_type', 'printer')
        ->where('slug', 'nederlandse-printer')
        ->first();

    expect($printer)->not->toBeNull()
        ->and($printer->title)->toBe('Nederlandse Printer')
        ->and($printer->content)->toBe('<p>Nederlandse inhoud</p>');

    $translation = Translation::findByModel($printer, 'en');

    expect($translation)->not->toBeNull()
        ->and($translation->name)->toBe('English Printer')
        ->and($translation->slug)->toBe('english-printer')
        ->and($translation->fields['title'])->toBe('English Printer')
        ->and($translation->fields['content'])->toBe('<p>English content</p>')
        ->and($translation->fields['subtitle'])->toBe('English subtitle');
});

it('switches printer basic information between locales and saves translations', function (): void {
    $printer = Post::factory()->printer()->create([
        'title' => 'Nederlandse Printer',
        'slug' => 'nederlandse-printer',
        'excerpt' => 'Nederlandse samenvatting',
        'content' => '<p>Nederlandse inhoud</p>',
    ]);

    $printer->meta()->create([
        'meta_key' => 'subtitle',
        'meta_value' => 'Nederlandse ondertitel',
    ]);

    Translation::createForModel($printer, 'en', [
        'name' => 'English Printer',
        'slug' => 'english-printer',
        'title' => 'English Printer',
        'subtitle' => 'English subtitle',
        'excerpt' => 'English excerpt',
        'content' => '<p>English content</p>',
    ]);

    Livewire::test('printers.create-update', ['printer' => $printer])
        ->assertSet('selectedLocale', 'nl')
        ->assertSet('title', 'Nederlandse Printer')
        ->call('switchLocale', 'en')
        ->assertSet('title', 'English Printer')
        ->assertSet('subtitle', 'English subtitle')
        ->set('title', 'Updated English Printer')
        ->set('content', '<p>Updated English content</p>')
        ->call('switchLocale', 'nl')
        ->assertSet('title', 'Nederlandse Printer')
        ->call('switchLocale', 'en')
        ->assertSet('title', 'Updated English Printer')
        ->call('save')
        ->assertHasNoErrors();

    $translation = Translation::findByModel($printer->refresh(), 'en');

    expect($printer->title)->toBe('Nederlandse Printer')
        ->and($translation->name)->toBe('Updated English Printer')
        ->and($translation->fields['title'])->toBe('Updated English Printer')
        ->and($translation->fields['content'])->toBe('<p>Updated English content</p>');
});

it('loads and saves printer technical details using vanilo properties', function (): void {
    $printMethod = Property::create([
        'name' => 'Printmethode',
        'slug' => 'printmethode',
        'type' => 'text',
    ]);

    $thermalDirect = PropertyValue::create([
        'property_id' => $printMethod->id,
        'value' => 'TD',
        'title' => 'TD',
    ]);

    $thermalTransfer = PropertyValue::create([
        'property_id' => $printMethod->id,
        'value' => 'TT',
        'title' => 'TT',
    ]);

    $printer = Post::factory()->printer()->create([
        'title' => 'Property Printer',
        'slug' => 'property-printer',
        'content' => '<p>Printer content</p>',
        'template' => 'default',
    ]);

    $printer->propertyValues()->attach([$thermalDirect->id, $thermalTransfer->id]);

    Livewire::test('printers.create-update', ['printer' => $printer])
        ->assertSet("printer_properties.{$printMethod->id}", ['TD', 'TT'])
        ->set("printer_properties.{$printMethod->id}", ['TT'])
        ->call('save')
        ->assertHasNoErrors();

    $selectedValues = $printer->fresh('propertyValues.property')
        ->propertyValues
        ->where('property_id', $printMethod->id)
        ->pluck('value')
        ->values()
        ->all();

    expect($selectedValues)->toBe(['TT']);
});

it('saves and reloads the printer url product link', function (): void {
    Config::set('app.frontend_url', 'https://businesslabels.nl');

    Property::create([
        'name' => 'Printer Url',
        'slug' => 'printer-url',
        'type' => 'text',
    ]);

    Product::create([
        'name' => 'Epson ColorWorks C6000',
        'title' => 'Epson ColorWorks C6000',
        'slug' => 'epson-colorworks-c6000',
        'sku' => 'EPSON-C6000',
        'state' => 'active',
    ]);

    $printer = Post::factory()->printer()->create([
        'title' => 'C6000 Finder Entry',
        'slug' => 'c6000-finder-entry',
    ]);

    $expectedUrl = 'https://businesslabels.nl/product/epson-colorworks-c6000/';

    Livewire::test('printers.create-update', ['printer' => $printer])
        ->assertSet('printer_url_value', '')
        ->set('printer_url_value', $expectedUrl)
        ->call('save')
        ->assertHasNoErrors();

    // Stored as a single property value on the printer.
    $stored = $printer->fresh('propertyValues')->propertyValues->pluck('value')->all();
    expect($stored)->toContain($expectedUrl);

    // Reloads on a fresh mount (the persistence the dropdown previously lost).
    Livewire::test('printers.create-update', ['printer' => $printer->fresh()])
        ->assertSet('printer_url_value', $expectedUrl);
});

/**
 * @return array<string, mixed>
 */
function printerPayload(
    int $id,
    string $title,
    string $slug,
    string $content,
    string $subtitle,
    array $translations,
): array {
    return [
        'id' => $id,
        'slug' => $slug,
        'status' => 'publish',
        'title' => ['rendered' => $title],
        'content' => ['rendered' => $content],
        'excerpt' => ['rendered' => ''],
        'featured_media' => 0,
        'translations' => $translations,
        'acf' => [
            'printers_sub_title' => $subtitle,
            'kern' => ['76'],
            'label_breedte' => '104mm',
            'labeltype' => 'Die-cut',
            'max_buiten_diameter' => '127mm',
            'widths' => ['104'],
            'druktype' => ['thermal-transfer'],
            'buiten_diameter' => ['127'],
            'detectie' => ['gap'],
            'meta-checkbox' => false,
            'printer_kopen' => 'https://example.com/printer',
        ],
    ];
}
