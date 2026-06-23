<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove unique constraints from article_number columns to allow
     * duplicate values during WooCommerce imports. This prevents
     * constraint violation errors when syncing products with duplicate
     * article numbers.
     */
    public function up(): void
    {
        // Drop unique constraint from products table
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['article_number']);
        });

        // Drop unique constraint from master_products table
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropUnique(['article_number']);
        });

        // Drop unique constraint from group_products table (if exists)
        if (Schema::hasTable('group_products')) {
            Schema::table('group_products', function (Blueprint $table) {
                $table->dropUnique(['article_number']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Re-add unique constraints (will fail if duplicates exist).
     */
    public function down(): void
    {
        // Re-add unique constraint to products table
        Schema::table('products', function (Blueprint $table) {
            $table->unique('article_number');
        });

        // Re-add unique constraint to master_products table
        Schema::table('master_products', function (Blueprint $table) {
            $table->unique('article_number');
        });

        // Re-add unique constraint to group_products table (if exists)
        if (Schema::hasTable('group_products')) {
            Schema::table('group_products', function (Blueprint $table) {
                $table->unique('article_number');
            });
        }
    }
};
