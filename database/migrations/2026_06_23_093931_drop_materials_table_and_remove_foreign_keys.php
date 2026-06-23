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
            $table->dropConstrainedForeignId('material_id');
        });

        Schema::table('master_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
        });

        Schema::table('group_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
        });

        Schema::dropIfExists('materials');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->json('specifications')->nullable();
            $table->string('code')->nullable();
            $table->string('brand')->nullable();
            $table->string('status')->default('active');
            $table->string('print_method')->nullable();
            $table->string('base_material')->nullable();
            $table->string('finish')->nullable();
            $table->string('adhesive')->nullable();
            $table->string('supplier')->nullable();
            $table->string('supplier_reference')->nullable();
            $table->float('price_per_sq_meter')->nullable();
            $table->string('certificate')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('master_products', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('group_products', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
        });
    }
};
