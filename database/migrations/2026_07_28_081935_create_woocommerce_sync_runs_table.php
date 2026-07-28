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
        Schema::create('woocommerce_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 24);
            $table->string('domain', 24)->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('current_page')->default(1);
            $table->unsignedInteger('total_pages')->nullable();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('disabled')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('options')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('requested_since')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index(['domain', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('woocommerce_sync_runs');
    }
};
