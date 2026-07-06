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
        Schema::dropIfExists('printer_product');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('printer_product', function (Blueprint $table) {
            $table->foreignId('printer_id')->constrained('posts')->cascadeOnDelete();
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedBigInteger('master_product_id')->nullable();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('master_product_id')->references('id')->on('master_products')->cascadeOnDelete();

            $table->unique(['printer_id', 'product_id']);
            $table->unique(['printer_id', 'master_product_id']);
        });
    }
};
