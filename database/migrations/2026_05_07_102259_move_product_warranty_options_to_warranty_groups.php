<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_warranty_options', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->unsignedInteger('product_id')->nullable()->change();
            $table->foreignId('warranty_group_id')
                ->nullable()
                ->after('product_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->boolean('is_default')->default(false)->after('description');
            $table->index(['warranty_group_id', 'is_active', 'is_default', 'sort_order'], 'pwo_group_active_default_sort_index');
        });

        if (DB::connection()->pretending()) {
            return;
        }

        DB::table('product_warranty_options')
            ->select('product_id')
            ->whereNotNull('product_id')
            ->distinct()
            ->orderBy('product_id')
            ->cursor()
            ->each(function (object $row): void {
                $product = DB::table('products')->where('id', $row->product_id)->first(['id', 'title', 'name', 'sku']);

                if (! $product) {
                    return;
                }

                $groupId = DB::table('warranty_groups')->insertGetId([
                    'name' => trim((string) ($product->title ?: $product->name ?: $product->sku ?: 'Product '.$product->id)).' Warranty',
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['warranty_group_id' => $groupId]);

                DB::table('product_warranty_options')
                    ->where('product_id', $product->id)
                    ->update(['warranty_group_id' => $groupId]);

                $defaultOption = DB::table('product_warranty_options')
                    ->where('warranty_group_id', $groupId)
                    ->where('is_active', true)
                    ->where('price', 0)
                    ->orderBy('sort_order')
                    ->orderBy('duration_months')
                    ->first(['id']);

                if (! $defaultOption) {
                    $defaultOptionId = DB::table('product_warranty_options')->insertGetId([
                        'product_id' => $product->id,
                        'warranty_group_id' => $groupId,
                        'name' => 'Included warranty',
                        'duration_months' => 0,
                        'price' => 0,
                        'description' => null,
                        'is_default' => true,
                        'is_active' => true,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $defaultOption = (object) ['id' => $defaultOptionId];
                }

                DB::table('product_warranty_options')
                    ->where('warranty_group_id', $groupId)
                    ->where('id', '!=', $defaultOption->id)
                    ->update(['is_default' => false]);

                DB::table('product_warranty_options')
                    ->where('id', $defaultOption->id)
                    ->update(['is_default' => true]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! DB::connection()->pretending()) {
            DB::table('product_warranty_options')
                ->whereNotNull('warranty_group_id')
                ->orderBy('warranty_group_id')
                ->cursor()
                ->each(function (object $option): void {
                    $productId = DB::table('products')
                        ->where('warranty_group_id', $option->warranty_group_id)
                        ->value('id');

                    if ($productId) {
                        DB::table('product_warranty_options')
                            ->where('id', $option->id)
                            ->update(['product_id' => $productId]);
                    }
                });
        }

        Schema::table('product_warranty_options', function (Blueprint $table) {
            $table->dropIndex('pwo_group_active_default_sort_index');
            $table->dropConstrainedForeignId('warranty_group_id');
            $table->dropColumn(['is_default']);
            $table->unsignedInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }
};
