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
            $table->text('meta_title_nl')->nullable()->after('meta_title');
            $table->text('meta_title_en')->nullable()->after('meta_title_nl');
            $table->text('meta_description_nl')->nullable()->after('meta_description');
            $table->text('meta_description_en')->nullable()->after('meta_description_nl');
        });

        Schema::table('master_products', function (Blueprint $table) {
            $table->text('meta_title_nl')->nullable()->after('ext_title');
            $table->text('meta_title_en')->nullable()->after('meta_title_nl');
            $table->text('meta_description_nl')->nullable()->after('meta_description');
            $table->text('meta_description_en')->nullable()->after('meta_description_nl');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['meta_title_nl', 'meta_title_en', 'meta_description_nl', 'meta_description_en']);
        });

        Schema::table('master_products', function (Blueprint $table) {
            $table->dropColumn(['meta_title_nl', 'meta_title_en', 'meta_description_nl', 'meta_description_en']);
        });
    }
};
