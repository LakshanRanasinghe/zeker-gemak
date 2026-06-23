<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('warranty_brand')->nullable()->after('product_template');
            $table->unsignedTinyInteger('warranty_duration_years')->nullable()->after('warranty_brand');
            $table->boolean('exclude_from_custom_warranty')->default(false)->after('warranty_duration_years');
            $table->index(['product_template', 'warranty_brand'], 'products_template_warranty_brand_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_template_warranty_brand_index');
            $table->dropColumn(['warranty_brand', 'warranty_duration_years', 'exclude_from_custom_warranty']);
        });
    }
};
