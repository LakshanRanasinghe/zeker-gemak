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
        Schema::create('country_shipping_rules', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->unique();
            $table->string('country_name');
            $table->decimal('shipping_cost', 10, 2);
            $table->decimal('free_shipping_threshold', 10, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_shipping_rules');
    }
};
