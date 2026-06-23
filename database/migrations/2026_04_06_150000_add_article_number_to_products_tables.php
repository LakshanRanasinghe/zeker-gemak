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
            if (! Schema::hasColumn('products', 'article_number')) {
                $table->string('article_number')->after('sku')->nullable()->unique();
            }
        });

        Schema::table('master_products', function (Blueprint $table) {
            if (! Schema::hasColumn('master_products', 'article_number')) {
                $table->string('article_number')->after('slug')->nullable()->unique();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('article_number');
        });

        Schema::table('master_products', function (Blueprint $table) {
            $table->dropColumn('article_number');
        });
    }
};
