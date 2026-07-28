<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('woocommerce_category_taxon_mappings')) {
            $ambiguousRemoteId = DB::table('woocommerce_category_taxon_mappings')
                ->select('woocommerce_category_id')
                ->groupBy('woocommerce_category_id')
                ->havingRaw('COUNT(DISTINCT taxon_id) > 1')
                ->exists();
            $ambiguousTaxon = DB::table('woocommerce_category_taxon_mappings')
                ->select('taxon_id')
                ->groupBy('taxon_id')
                ->havingRaw('COUNT(DISTINCT woocommerce_category_id) > 1')
                ->exists();

            if ($ambiguousRemoteId || $ambiguousTaxon) {
                throw new RuntimeException('Ambiguous WooCommerce category mappings must be resolved before cleanup.');
            }

            DB::table('woocommerce_category_taxon_mappings')
                ->orderBy('id')
                ->each(function (object $mapping): void {
                    DB::table('taxons')
                        ->where('id', $mapping->taxon_id)
                        ->whereNull('woocommerce_id')
                        ->update(['woocommerce_id' => $mapping->woocommerce_category_id]);
                });
        }

        $removedMorphTypes = [
            'master_product',
            'post',
            'App\Models\MasterProduct',
            'App\Models\Post',
            'Vanilo\Foundation\Models\MasterProduct',
        ];

        foreach ([
            'media' => 'model_type',
            'translations' => 'translatable_type',
            'model_taxons' => 'model_type',
            'model_property_values' => 'model_type',
            'model_videos' => 'model_type',
            'channelables' => 'channelable_type',
        ] as $table => $typeColumn) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->whereIn($typeColumn, $removedMorphTypes)->delete();
        }

        if (Schema::hasTable('favorite_products')) {
            DB::table('favorite_products')->where('product_type', 'variable')->delete();
        }

        if (Schema::hasTable('customer_reviews')) {
            DB::table('customer_reviews')->where('product_type', 'variable')->delete();
        }

        if (Schema::hasTable('group_product_items') && Schema::hasColumn('group_product_items', 'source_type')) {
            DB::table('group_product_items')->where('source_type', 'variable')->delete();
        }

        if (Schema::hasTable('product_relations')) {
            DB::table('product_relations')->where('relation_type', 'printer')->delete();
        }

        Schema::dropIfExists('master_product_metas');
        Schema::dropIfExists('master_product_variants');
        Schema::dropIfExists('master_products');
        Schema::dropIfExists('post_meta');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('woocommerce_category_taxon_mappings');

        Schema::dropIfExists('oauth_device_codes');
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_auth_codes');
        Schema::dropIfExists('oauth_clients');
    }

    public function down(): void
    {
        throw new LogicException('Removed module data must be restored from the pre-deployment backup.');
    }
};
