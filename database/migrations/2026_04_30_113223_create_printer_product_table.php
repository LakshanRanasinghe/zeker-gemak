<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('printer_product', function (Blueprint $table) {
            $table->foreignId('printer_id')->constrained('posts')->cascadeOnDelete();
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedBigInteger('master_product_id')->nullable();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('master_product_id')->references('id')->on('master_products')->cascadeOnDelete();

            // Prevent duplicate printer assignments
            $table->unique(['printer_id', 'product_id']);
            $table->unique(['printer_id', 'master_product_id']);
        });

        // Add check constraint only for MySQL (SQLite doesn't support ALTER TABLE ADD CONSTRAINT)
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE printer_product ADD CONSTRAINT check_one_product_type CHECK ((product_id IS NOT NULL AND master_product_id IS NULL) OR (product_id IS NULL AND master_product_id IS NOT NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printer_product');
    }
};
