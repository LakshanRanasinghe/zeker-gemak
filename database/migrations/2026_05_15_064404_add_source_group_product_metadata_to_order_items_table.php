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
        Schema::table('order_items', function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('source_group_product_id')->nullable()->after('configuration');
            $blueprint->string('source_group_product_name')->nullable()->after('source_group_product_id');
            $blueprint->string('source_group_product_sku')->nullable()->after('source_group_product_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $blueprint) {
            $blueprint->dropColumn([
                'source_group_product_id',
                'source_group_product_name',
                'source_group_product_sku',
            ]);
        });
    }
};
