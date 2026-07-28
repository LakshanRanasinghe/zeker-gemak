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
        Schema::table('woocommerce_sync_runs', function (Blueprint $table) {
            $table->unsignedInteger('media_pending')->default(0)->after('failed');
            $table->unsignedInteger('media_processed')->default(0)->after('media_pending');
            $table->unsignedInteger('media_failed')->default(0)->after('media_processed');
            $table->timestamp('reindex_queued_at')->nullable()->after('failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('woocommerce_sync_runs', function (Blueprint $table) {
            $table->dropColumn([
                'media_pending',
                'media_processed',
                'media_failed',
                'reindex_queued_at',
            ]);
        });
    }
};
