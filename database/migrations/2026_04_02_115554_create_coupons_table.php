<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();

            // General
            $table->enum('discount_type', ['percentage', 'fixed_cart', 'fixed_product'])->default('percentage');
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('allow_free_shipping')->default(false);
            $table->date('expiry_date')->nullable();

            // Usage Restriction
            $table->decimal('minimum_spend', 10, 2)->nullable();
            $table->decimal('maximum_spend', 10, 2)->nullable();
            $table->boolean('individual_use')->default(false);
            $table->boolean('exclude_sale_items')->default(false);
            $table->json('product_ids')->nullable();
            $table->json('exclude_product_ids')->nullable();
            $table->json('category_ids')->nullable();
            $table->json('exclude_category_ids')->nullable();
            $table->json('allowed_emails')->nullable();

            // Usage Limits
            $table->unsignedInteger('usage_limit_per_coupon')->nullable();
            $table->unsignedInteger('limit_usage_to_x_items')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('usage_count')->default(0);

            $table->timestamps();
            // $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_coupons');
    }
};
