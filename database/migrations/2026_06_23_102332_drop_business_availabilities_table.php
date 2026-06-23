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
        Schema::dropIfExists('business_availabilities');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('business_availabilities', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->boolean('is_fully_unavailable')->default(false);
            $table->time('unavailable_start_time')->nullable();
            $table->time('unavailable_end_time')->nullable();
            $table->timestamps();
        });
    }
};
