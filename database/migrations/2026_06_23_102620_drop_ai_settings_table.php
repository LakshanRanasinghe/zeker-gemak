<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ai_settings');
    }

    public function down(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 32)->default('product');
            $table->text('role_description')->nullable();
            $table->text('taxonomy_guidelines')->nullable();
            $table->string('tone', 32)->default('professional');
            $table->text('style_notes')->nullable();
            $table->json('language_settings')->nullable();
            $table->unsignedSmallInteger('wc_excerpt')->default(40);
            $table->unsignedSmallInteger('wc_short_description')->default(75);
            $table->unsignedSmallInteger('wc_content')->default(300);
            $table->unsignedSmallInteger('wc_product_information')->default(150);
            $table->json('default_fields_en')->nullable();
            $table->json('default_fields_nl')->nullable();
            $table->string('default_language', 8)->default('en');
            $table->boolean('auto_detect_locale')->default(true);
            $table->timestamps();
        });
    }
};
