<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public-facing FAQ pages (a FAQ hub per topic / per language).
     * The hero image is stored via Spatie Media Library.
     */
    public function up(): void
    {
        Schema::create('faq_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('intro')->nullable();
            $table->string('support_title')->nullable();
            $table->text('support_text')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_pages');
    }
};
