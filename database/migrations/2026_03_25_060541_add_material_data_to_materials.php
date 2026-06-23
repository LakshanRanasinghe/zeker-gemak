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
        Schema::table('materials', function (Blueprint $table) {
            // From LabelPilots
            $table->string('code')->nullable();
            $table->string('brand')->nullable();
            $table->string('status')->default('active');
            $table->string('print_method')->nullable();
            $table->string('base_material')->nullable();
            $table->string('finish')->nullable();
            $table->string('adhesive')->nullable();

            $table->string('supplier')->nullable();
            $table->string('supplier_reference')->nullable();
            $table->float('price_per_sq_meter')->nullable();
            $table->string('certificate')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            //
        });
    }
};
