<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->foreignId('material_category_id')->nullable()->constrained()->nullOnDelete();
            $table->json('specifications')->nullable();

            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('master_products', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
        });

        Schema::dropIfExists('materials');
        Schema::dropIfExists('material_categories');
    }
};
