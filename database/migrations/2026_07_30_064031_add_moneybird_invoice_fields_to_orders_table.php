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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('moneybird_invoice_id')->nullable()->unique();
            $table->string('moneybird_invoice_number')->nullable();
            $table->string('moneybird_invoice_status')->nullable();
            $table->string('moneybird_invoice_url', 1024)->nullable();
            $table->timestamp('moneybird_invoice_sent_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'moneybird_invoice_id',
                'moneybird_invoice_number',
                'moneybird_invoice_status',
                'moneybird_invoice_url',
                'moneybird_invoice_sent_at',
            ]);
        });
    }
};
