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
        Schema::dropIfExists('printer_warranty_bindings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('printer_warranty_bindings', function (Blueprint $table) {
            $table->unsignedInteger('printer_id');
            $table->unsignedInteger('warranty_id');
            $table->timestamps();

            $table->primary(['printer_id', 'warranty_id']);
            $table->index('warranty_id', 'pwb_warranty_index');

            $table->foreign('printer_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('warranty_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
