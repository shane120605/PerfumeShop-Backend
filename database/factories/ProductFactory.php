<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 800, 5000),
            'stock' => fake()->numberBetween(5, 30),
            'size' => '50 ml',
            'image_number' => fake()->numberBetween(1, 4),
            'notes_top' => 'Bergamot',
            'notes_heart' => 'Rose',
            'notes_base' => 'Musk',
        ];
    }
}
