<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sections belong to a single FAQ page. They are drag-and-drop
     * sortable via `sort_order` and each one auto-generates an in-page
     * navigation anchor on the frontend.
     */
    public function up(): void
    {
        Schema::create('faq_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_page_id')->constrained('faq_pages')->cascadeOnDelete();
            $table->string('name');
            $table->string('subtitle')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['faq_page_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_sections');
    }
};
