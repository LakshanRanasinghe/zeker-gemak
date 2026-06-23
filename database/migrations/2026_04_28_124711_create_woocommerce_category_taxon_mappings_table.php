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
        Schema::create('woocommerce_category_taxon_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('woocommerce_category_id');
            $table->unsignedInteger('taxon_id');
            $table->string('slug')->nullable();
            $table->string('source')->default('woocommerce');
            $table->timestamps();

            $table->unique(['source', 'woocommerce_category_id'], 'wc_category_source_unique');
            $table->foreign('taxon_id')->references('id')->on('taxons')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('woocommerce_category_taxon_mappings');
    }
};
