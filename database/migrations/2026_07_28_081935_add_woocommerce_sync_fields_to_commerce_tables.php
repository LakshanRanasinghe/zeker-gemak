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
            $table->unsignedBigInteger('woocommerce_id')->nullable()->unique()->after('id');
            $table->timestamp('synced_at')->nullable()->index()->after('updated_at');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('woocommerce_id')->nullable()->unique()->after('id');
            $table->foreignId('user_id')->nullable()->unique()->after('woocommerce_id')
                ->constrained()->nullOnDelete();
            $table->timestamp('synced_at')->nullable()->index()->after('updated_at');
            $table->index('email');
        });

        Schema::table('taxons', function (Blueprint $table) {
            $table->unsignedBigInteger('woocommerce_id')->nullable()->unique()->after('id');
            $table->unsignedBigInteger('woocommerce_parent_id')->nullable()->index()->after('woocommerce_id');
            $table->boolean('is_active')->default(true)->index()->after('woocommerce_id');
            $table->timestamp('synced_at')->nullable()->index()->after('updated_at');
        });

        Schema::table('discount_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('woocommerce_id')->nullable()->unique()->after('id');
            $table->json('tiers')->nullable()->after('discounts');
            $table->boolean('is_active')->default(true)->index()->after('tiers');
            $table->timestamp('synced_at')->nullable()->index()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discount_groups', function (Blueprint $table) {
            $table->dropColumn(['woocommerce_id', 'tiers', 'is_active', 'synced_at']);
        });

        Schema::table('taxons', function (Blueprint $table) {
            $table->dropColumn(['woocommerce_id', 'woocommerce_parent_id', 'is_active', 'synced_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['email']);
            $table->dropColumn(['woocommerce_id', 'user_id', 'synced_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['woocommerce_id', 'synced_at']);
        });
    }
};
