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
        // Drop foreign key constraint and column from materials table
        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_category_id');
        });

        // Drop material_categories table
        Schema::dropIfExists('material_categories');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate material_categories table
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Re-add foreign key constraint to materials table
        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('material_category_id')->nullable()->after('description')->constrained()->nullOnDelete();
        });
    }
};
