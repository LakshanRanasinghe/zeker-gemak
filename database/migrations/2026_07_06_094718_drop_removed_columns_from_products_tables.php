<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop index that contains dropped columns
            $table->dropIndex('products_template_warranty_brand_index');

            // Drop foreign key for warranty_group_id
            $table->dropForeign(['warranty_group_id']);

            $table->dropColumn([
                'product_template',
                'product_information',
                'make',
                'material_information',
                'jeritech_stock',
                'warranty_brand',
                'warranty_duration_years',
                'exclude_from_custom_warranty',
                'warranty_group_id',
            ]);
        });

        Schema::table('master_products', function (Blueprint $table) {
            $table->dropColumn([
                'product_template',
                'product_information',
                'make',
                'material_information',
                'jeritech_stock',
            ]);
        });

        Schema::table('group_products', function (Blueprint $table) {
            $table->dropColumn([
                'product_template',
                'product_information',
                'make',
                'material_information',
                'jeritech_stock',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to revert since this is a clean deletion requested by the user.
    }
};
