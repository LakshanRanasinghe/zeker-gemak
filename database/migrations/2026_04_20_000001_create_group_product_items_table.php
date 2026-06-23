<?php

use App\Models\GroupProduct;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(GroupProduct::class, 'group_product_id')
                ->constrained('group_products')
                ->cascadeOnDelete();
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')
                ->references('id')->on('products')
                ->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            // Ensure each product can only be added once per group
            $table->unique(['group_product_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_product_items');
    }
};
