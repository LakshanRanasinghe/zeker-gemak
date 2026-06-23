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
        Schema::table('business_availabilities', function (Blueprint $table) {
            $table->boolean('is_fully_unavailable')->default(false)->after('date');
            $table->time('unavailable_start_time')->nullable()->after('is_fully_unavailable');
            $table->time('unavailable_end_time')->nullable()->after('unavailable_start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_availabilities', function (Blueprint $table) {
            $table->dropColumn(['is_fully_unavailable', 'unavailable_start_time', 'unavailable_end_time']);
        });
    }
};
