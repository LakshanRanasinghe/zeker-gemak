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
            $table->enum('product_type', ['simple', 'variable'])->default('simple');
            $table->string('article_number')->nullable()->unique()->after('sku');
            $table->string('title')->nullable()->after('name');
            $table->longText('content')->nullable()->after('excerpt');
            $table->string('product_template')->nullable()->after('description');
            $table->text('product_information')->nullable()->after('product_template');
            $table->string('make')->nullable()->after('product_information');
            $table->string('material_information')->nullable()->after('make');
            $table->integer('packaging_unit')->nullable()->after('material_information');
            $table->integer('jeritech_stock')->nullable()->after('packaging_unit');
            $table->integer('delivery_dates_no_stock')->nullable()->after('jeritech_stock');
            $table->integer('delivery_dates_in_stock')->nullable()->after('delivery_dates_no_stock');
            $table->integer('packing_group')->nullable()->after('delivery_dates_in_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'product_type',
                'article_number',
                'title',
                'content',
                'product_template',
                'product_information',
                'make',
                'material_information',
                'packaging_unit',
                'jeritech_stock',
                'delivery_dates_no_stock',
                'delivery_dates_in_stock',
                'packing_group',
            ]);
        });
    }
};
