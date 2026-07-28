<?php

use App\Livewire\DiscountGroups;
use App\Livewire\PageTable;
use App\Livewire\PostTable;
use App\Livewire\PrinterTable;
use App\Livewire\ProductTable;
use App\Livewire\TaxonomyTable;
use App\Models\DiscountGroup;
use App\Models\MasterProduct;
use App\Models\Post;
use App\Models\PostMeta;
use App\Models\Product;
use App\Models\Taxon;
use App\Models\WysiwygMedia;
use Flux\Flux;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Vanilo\Channel\Models\Channel;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Properties\Models\Property;
use Vanilo\Properties\Models\PropertyValue;
use Vanilo\Translation\Models\Translation;
use Vanilo\Video\Models\Video;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');

    Product::disableSearchSyncing();

    Storage::fake('public');
});

afterEach(function () {
    Product::enableSearchSyncing();
});

function deletion_test_attachment_html(int $wysiwygMediaId): string
{
    $json = htmlspecialchars(json_encode(['wysiwygMediaId' => $wysiwygMediaId]), ENT_QUOTES, 'UTF-8');

    return '<div data-trix-attachment="'.$json.'"></div>';
}

function deletion_test_translation_for($model, array $fields): Translation
{
    return Translation::createForModel($model, 'nl', $fields);
}

function deletion_test_channel(string $name): Channel
{
    return Channel::create([
        'name' => $name,
        'slug' => str($name)->slug()->value(),
        'configuration' => [],
    ]);
}

function deletion_test_video(string $reference): Video
{
    return Video::create([
        'driver' => 'youtube',
        'reference' => $reference,
        'title' => $reference,
    ]);
}

function deletion_test_property_value(string $name, string $value): PropertyValue
{
    $property = Property::create([
        'name' => $name,
        'type' => 'text',
        'slug' => str($name)->slug()->value(),
    ]);

    return PropertyValue::create([
        'property_id' => $property->id,
        'value' => $value,
        'title' => $value,
    ]);
}

function deletion_test_call_component_method(string $component, string $method, mixed ...$arguments): void
{
    Flux::shouldReceive('toast')->once();

    app($component)->{$method}(...$arguments);
}

dataset('post-like deletion entry points', [
    'posts table' => [PostTable::class, 'deletePost', 'post'],
    'pages table' => [PageTable::class, 'deletePage', 'page'],
    'printers table' => [PrinterTable::class, 'deletePrinter', 'printer'],
]);

it('deletes post-like entries with translations, wysiwyg media, and meta rows', function (
    string $component,
    string $method,
    string $postType,
) {
    $this->markTestSkipped('Posts, pages, and printers were removed from zeker-gemak.');

    $contentMedia = WysiwygMedia::create();
    $excerptMedia = WysiwygMedia::create();

    $post = Post::create([
        'title' => ucfirst($postType).' Entry',
        'content' => deletion_test_attachment_html($contentMedia->id),
        'excerpt' => deletion_test_attachment_html($excerptMedia->id),
        'slug' => $postType.'-entry',
        'status' => 'draft',
        'post_type' => $postType,
        'template' => 'default',
    ]);

    $translation = deletion_test_translation_for($post, [
        'title' => ucfirst($postType).' Nederlands',
        'slug' => $postType.'-entry-nl',
    ]);

    $meta = PostMeta::create([
        'post_id' => $post->id,
        'meta_key' => 'seo_title',
        'meta_value' => 'SEO',
    ]);

    deletion_test_call_component_method($component, $method, $post->id);

    expect(Post::find($post->id))->toBeNull();
    expect(Translation::find($translation->id))->toBeNull();
    expect(PostMeta::find($meta->id))->toBeNull();
    expect(WysiwygMedia::find($contentMedia->id))->toBeNull();
    expect(WysiwygMedia::find($excerptMedia->id))->toBeNull();
})->with('post-like deletion entry points');

it('deletes simple products and removes all confirmed owned relations', function () {
    $channel = deletion_test_channel('Catalog');
    $video = deletion_test_video('simple-product-video');
    $propertyValue = deletion_test_property_value('Finish', 'Matte');
    $taxonomy = Taxonomy::create([
        'name' => 'Categories',
        'slug' => 'categories',
    ]);
    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Labels',
        'slug' => 'labels',
    ]);
    $descriptionMedia = WysiwygMedia::create();

    $product = Product::create([
        'name' => 'Zebra Label',
        'title' => 'Zebra Label',
        'slug' => 'zebra-label',
        'sku' => 'ZEBRA-1',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'description' => deletion_test_attachment_html($descriptionMedia->id),
    ]);

    $productMorph = $product->getMorphClass();

    $translation = deletion_test_translation_for($product, [
        'name' => 'Zebra Etiket',
        'slug' => 'zebra-etiket',
    ]);

    $product->metas()->create([
        'meta_key' => 'brand',
        'meta_value' => 'zebra',
    ]);

    $product->channels()->attach($channel->id);
    $product->taxons()->attach($taxon->id);
    $product->propertyValues()->attach($propertyValue->id);
    $product->videos()->attach($video->id);

    $product->addMedia(UploadedFile::fake()->image('main.jpg'))
        ->toMediaCollection('main', 'public');
    $product->addMedia(UploadedFile::fake()->image('gallery.jpg'))
        ->toMediaCollection('gallery', 'public');

    deletion_test_call_component_method(ProductTable::class, 'deleteProduct', $product->id, 'simple');

    expect(Product::find($product->id))->toBeNull();
    expect(Translation::find($translation->id))->toBeNull();
    expect(WysiwygMedia::find($descriptionMedia->id))->toBeNull();
    expect(DB::table('product_metas')->where('product_id', $product->id)->exists())->toBeFalse();
    expect(DB::table('channelables')->where('channelable_type', $productMorph)->where('channelable_id', $product->id)->exists())->toBeFalse();
    expect(DB::table('model_taxons')->where('model_type', $productMorph)->where('model_id', $product->id)->exists())->toBeFalse();
    expect(DB::table('model_property_values')->where('model_type', $productMorph)->where('model_id', $product->id)->exists())->toBeFalse();
    expect(DB::table('model_videos')->where('model_type', $productMorph)->where('model_id', $product->id)->exists())->toBeFalse();
    expect(Media::query()->where('model_type', $productMorph)->where('model_id', $product->id)->exists())->toBeFalse();
    expect(Video::find($video->id))->not->toBeNull();
});

it('deletes master products and cleans master and variant owned relations', function () {
    $this->markTestSkipped('Master products were removed from zeker-gemak.');

    $channel = deletion_test_channel('Wholesale');
    $video = deletion_test_video('master-product-video');
    $variantVideo = deletion_test_video('variant-video');
    $propertyValue = deletion_test_property_value('Core Size', '40mm');
    $variantPropertyValue = deletion_test_property_value('Color', 'White');
    $taxonomy = Taxonomy::create([
        'name' => 'Products',
        'slug' => 'products',
    ]);
    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Ribbons',
        'slug' => 'ribbons',
    ]);
    $descriptionMedia = WysiwygMedia::create();

    $master = MasterProduct::create([
        'name' => 'Industrial Ribbon',
        'title' => 'Industrial Ribbon',
        'slug' => 'industrial-ribbon',
        'price' => 25,
        'state' => 'active',
        'description' => deletion_test_attachment_html($descriptionMedia->id),
    ]);

    $masterMorph = $master->getMorphClass();

    $translation = deletion_test_translation_for($master, [
        'name' => 'Industrieel Lint',
        'slug' => 'industrieel-lint',
    ]);

    $master->metas()->create([
        'meta_key' => 'brand',
        'meta_value' => 'tsc',
    ]);

    $master->channels()->attach($channel->id);
    $master->taxons()->attach($taxon->id);
    $master->propertyValues()->attach($propertyValue->id);
    $master->videos()->attach($video->id);

    $master->addMedia(UploadedFile::fake()->image('master-main.jpg'))
        ->toMediaCollection('main', 'public');

    $variant = $master->createVariant([
        'sku' => 'RBN-100',
        'price' => 25,
        'stock' => 8,
    ]);

    $variantMorph = $variant->getMorphClass();

    $variant->propertyValues()->attach($variantPropertyValue->id);
    $variant->videos()->attach($variantVideo->id);
    $variant->addMedia(UploadedFile::fake()->image('variant-main.jpg'))
        ->toMediaCollection('main', 'public');

    deletion_test_call_component_method(ProductTable::class, 'deleteProduct', $master->id, 'variable');

    expect(MasterProduct::find($master->id))->toBeNull();
    expect(Translation::find($translation->id))->toBeNull();
    expect(WysiwygMedia::find($descriptionMedia->id))->toBeNull();
    expect(DB::table('master_product_metas')->where('master_product_id', $master->id)->exists())->toBeFalse();
    expect(DB::table('channelables')->where('channelable_type', $masterMorph)->where('channelable_id', $master->id)->exists())->toBeFalse();
    expect(DB::table('model_taxons')->where('model_type', $masterMorph)->where('model_id', $master->id)->exists())->toBeFalse();
    expect(DB::table('model_property_values')->where('model_type', $masterMorph)->where('model_id', $master->id)->exists())->toBeFalse();
    expect(DB::table('model_videos')->where('model_type', $masterMorph)->where('model_id', $master->id)->exists())->toBeFalse();
    expect(Media::query()->where('model_type', $masterMorph)->where('model_id', $master->id)->exists())->toBeFalse();
    expect(DB::table('master_product_variants')->where('id', $variant->id)->exists())->toBeFalse();
    expect(DB::table('model_property_values')->where('model_type', $variantMorph)->where('model_id', $variant->id)->exists())->toBeFalse();
    expect(DB::table('model_videos')->where('model_type', $variantMorph)->where('model_id', $variant->id)->exists())->toBeFalse();
    expect(Media::query()->where('model_type', $variantMorph)->where('model_id', $variant->id)->exists())->toBeFalse();
});

it('soft deletes discount groups without rewriting product assignments', function () {
    $discountGroup = DiscountGroup::create([
        'name' => 'Bulk Discounts',
        'discounts' => json_encode(['10' => 5]),
    ]);

    $product = Product::create([
        'name' => 'Discounted Label',
        'title' => 'Discounted Label',
        'slug' => 'discounted-label',
        'sku' => 'DISC-1',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'discount_group_id' => $discountGroup->id,
    ]);

    deletion_test_call_component_method(DiscountGroups::class, 'deleteDiscountGroup', $discountGroup->id);

    expect(DiscountGroup::find($discountGroup->id))->toBeNull();
    expect($product->refresh()->discount_group_id)->toBe($discountGroup->id);
});

it('deletes taxonomies through confirmed relations and cleans taxon side effects', function () {
    $channel = deletion_test_channel('Store');
    $taxonomyVideo = deletion_test_video('taxonomy-video');
    $taxonVideo = deletion_test_video('taxon-video');

    $taxonomy = Taxonomy::create([
        'name' => 'Catalog',
        'slug' => 'catalog',
    ]);

    $taxonomyMorph = $taxonomy->getMorphClass();

    $taxonomyTranslation = deletion_test_translation_for($taxonomy, [
        'name' => 'Catalogus',
        'slug' => 'catalogus',
    ]);

    $taxonomy->channels()->attach($channel->id);
    $taxonomy->videos()->attach($taxonomyVideo->id);
    $taxonomy->addMedia(UploadedFile::fake()->image('taxonomy.jpg'))
        ->toMediaCollection('default', 'public');

    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Labels',
        'slug' => 'labels',
    ]);

    $taxonMorph = $taxon->getMorphClass();

    $taxonTranslation = deletion_test_translation_for($taxon, [
        'name' => 'Etiketten',
        'slug' => 'etiketten',
    ]);

    $taxon->videos()->attach($taxonVideo->id);
    $taxon->addMedia(UploadedFile::fake()->image('taxon.jpg'))
        ->toMediaCollection('default', 'public');

    $product = Product::create([
        'name' => 'Taxon Product',
        'title' => 'Taxon Product',
        'slug' => 'taxon-product',
        'sku' => 'TAXON-1',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
    ]);
    $product->taxons()->attach($taxon->id);

    deletion_test_call_component_method(TaxonomyTable::class, 'deleteTaxonomy', $taxonomy->id);

    expect(Taxonomy::find($taxonomy->id))->toBeNull();
    expect(Taxon::find($taxon->id))->toBeNull();
    expect(Translation::find($taxonomyTranslation->id))->toBeNull();
    expect(Translation::find($taxonTranslation->id))->toBeNull();
    expect(DB::table('channelables')->where('channelable_type', $taxonomyMorph)->where('channelable_id', $taxonomy->id)->exists())->toBeFalse();
    expect(DB::table('model_videos')->where('model_type', $taxonomyMorph)->where('model_id', $taxonomy->id)->exists())->toBeFalse();
    expect(DB::table('model_videos')->where('model_type', $taxonMorph)->where('model_id', $taxon->id)->exists())->toBeFalse();
    expect(Media::query()->where('model_type', $taxonomyMorph)->where('model_id', $taxonomy->id)->exists())->toBeFalse();
    expect(Media::query()->where('model_type', $taxonMorph)->where('model_id', $taxon->id)->exists())->toBeFalse();
    expect(DB::table('model_taxons')->where('taxon_id', $taxon->id)->exists())->toBeFalse();
    expect(Product::find($product->id))->not->toBeNull();
});
