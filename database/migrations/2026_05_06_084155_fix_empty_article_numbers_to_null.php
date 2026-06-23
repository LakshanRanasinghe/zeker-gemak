<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert empty string article_numbers to NULL in products table
        DB::table('products')
            ->where('article_number', '=', '')
            ->update(['article_number' => null]);

        // Convert empty string article_numbers to NULL in master_products table
        DB::table('master_products')
            ->where('article_number', '=', '')
            ->update(['article_number' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this data cleanup
    }
};
