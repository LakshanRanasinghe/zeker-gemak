<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot linking reusable FAQ items to sections. A single FAQ item
     * may appear in many sections / many pages — this table is what
     * solves the content duplication problem. `sort_order` makes the
     * questions inside a section drag-and-drop sortable.
     */
    public function up(): void
    {
        Schema::create('faq_section_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_section_id')->constrained('faq_sections')->cascadeOnDelete();
            $table->foreignId('faq_item_id')->constrained('faq_items')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['faq_section_id', 'faq_item_id']);
            $table->index(['faq_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_section_item');
    }
};
