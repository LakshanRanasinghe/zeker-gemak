<?php

namespace Database\Factories;

use App\Models\GroupProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GroupProduct>
 */
class GroupProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'name' => $title,
            'title' => ucfirst($title),
            'subtitle' => fake()->sentence(6),
            'sku' => 'GRP-'.fake()->unique()->numerify('####'),
            'article_number' => 'ART-'.fake()->unique()->numerify('####'),
            'slug' => Str::slug($title),
            'price' => fake()->randomFloat(2, 50, 1000),
            'original_price' => fake()->optional()->randomFloat(2, 100, 1500),
            'excerpt' => fake()->sentence(15),
            'description' => fake()->paragraph(3),
            'content' => fake()->paragraph(5),
            'product_information' => fake()->optional()->paragraph(2),
            'meta_title' => fake()->optional()->sentence(8),
            'meta_description' => fake()->optional()->sentence(12),
            'product_template' => fake()->randomElement(['label', 'printer', 'accessory']),
            'state' => fake()->randomElement(['active', 'draft', 'unavailable']),
            'weight' => fake()->optional()->randomFloat(2, 0.1, 50),
            'width' => fake()->optional()->randomFloat(2, 5, 100),
            'height' => fake()->optional()->randomFloat(2, 5, 100),
            'length' => fake()->optional()->randomFloat(2, 5, 100),
            'make' => fake()->optional()->company(),
            'material_information' => fake()->optional()->words(5, true),
            'packaging_unit' => fake()->optional()->numberBetween(1, 100),
            'jeritech_stock' => fake()->optional()->numberBetween(0, 1000),
            'delivery_dates_no_stock' => fake()->optional()->numberBetween(1, 30),
            'delivery_dates_in_stock' => fake()->optional()->numberBetween(1, 7),
            'packing_group' => fake()->optional()->numberBetween(1, 10),
            'tax_category_id' => null,
            'material_id' => null,
            'discount_group_id' => null,
        ];
    }

    /**
     * Indicate that the group product is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'active',
        ]);
    }

    /**
     * Indicate that the group product is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'draft',
        ]);
    }

    /**
     * Indicate that the group product is unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'unavailable',
        ]);
    }

    /**
     * Set specific price.
     */
    public function price(float $price): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
        ]);
    }

    /**
     * With component products.
     */
    public function withComponents(int $count = 2): static
    {
        return $this->afterCreating(function (GroupProduct $groupProduct) use ($count) {
            $products = Product::factory()->count($count)->create();

            foreach ($products as $product) {
                $groupProduct->items()->create([
                    'product_id' => $product->id,
                    'quantity' => fake()->numberBetween(1, 5),
                ]);
            }
        });
    }
}
