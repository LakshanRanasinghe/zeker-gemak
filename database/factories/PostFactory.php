<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'post_type' => 'post',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function printer(): self
    {
        return $this->state(fn (array $attributes) => [
            'post_type' => 'printer',
        ]);
    }
}
