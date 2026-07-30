<?php

namespace Database\Factories;

use App\Models\CountryShippingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CountryShippingRule>
 */
class CountryShippingRuleFactory extends Factory
{
    protected $model = CountryShippingRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_code' => fake()->unique()->randomElement(['NL', 'BE']),
            'country_name' => fake()->randomElement(['Netherlands', 'Belgium']),
            'shipping_cost' => fake()->randomFloat(2, 5, 15),
            'free_shipping_threshold' => fake()->randomFloat(2, 50, 150),
            'is_active' => true,
        ];
    }
}
