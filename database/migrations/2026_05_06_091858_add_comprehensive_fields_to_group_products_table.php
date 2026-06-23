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
        Schema::table('group_products', function (Blueprint $table) {
            // Core product fields
            $table->string('name')->nullable()->after('title');
            $table->string('slug')->unique()->nullable()->after('sku');
            $table->string('subtitle')->nullable()->after('slug');
            $table->string('article_number')->unique()->nullable()->after('subtitle');

            // Pricing
            $table->decimal('original_price', 15, 4)->nullable()->after('price');

            // Content fields
            $table->string('excerpt', 500)->nullable()->after('original_price');
            $table->text('description')->nullable()->after('excerpt');
            $table->longText('content')->nullable()->after('description');
            $table->longText('product_information')->nullable()->after('content');

            // SEO fields
            $table->string('meta_title')->nullable()->after('product_information');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
            $table->string('meta_keywords')->nullable()->after('meta_description');

            // Product configuration
            $table->string('product_template')->nullable()->after('meta_keywords');
            $table->enum('state', ['draft', 'active', 'unavailable'])->default('active')->after('product_template');

            // Physical dimensions
            $table->decimal('weight', 12, 4)->nullable()->after('state');
            $table->decimal('width', 12, 4)->nullable()->after('weight');
            $table->decimal('height', 12, 4)->nullable()->after('width');
            $table->decimal('length', 12, 4)->nullable()->after('height');

            // Additional info
            $table->string('make')->nullable()->after('length');
            $table->string('material_information')->nullable()->after('make');
            $table->integer('packaging_unit')->nullable()->after('material_information');
            $table->integer('jeritech_stock')->nullable()->after('packaging_unit');
            $table->integer('delivery_dates_no_stock')->nullable()->after('jeritech_stock');
            $table->integer('delivery_dates_in_stock')->nullable()->after('delivery_dates_no_stock');
            $table->integer('packing_group')->nullable()->after('delivery_dates_in_stock');

            // Foreign keys
            $table->foreignId('tax_category_id')->nullable()->constrained('tax_categories')->nullOnDelete()->after('packing_group');
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete()->after('tax_category_id');
            $table->foreignId('discount_group_id')->nullable()->constrained('discount_groups')->nullOnDelete()->after('material_id');

            // Soft deletes
            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_products', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropForeign(['discount_group_id']);
            $table->dropForeign(['material_id']);
            $table->dropForeign(['tax_category_id']);
            $table->dropColumn([
                'name',
                'slug',
                'subtitle',
                'article_number',
                'original_price',
                'excerpt',
                'description',
                'content',
                'product_information',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'product_template',
                'state',
                'weight',
                'width',
                'height',
                'length',
                'make',
                'material_information',
                'packaging_unit',
                'jeritech_stock',
                'delivery_dates_no_stock',
                'delivery_dates_in_stock',
                'packing_group',
                'tax_category_id',
                'material_id',
                'discount_group_id',
            ]);
        });
    }
};
