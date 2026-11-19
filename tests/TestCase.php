<?php

namespace Tests;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function customer(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function admin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge(['price' => 100.00, 'stock' => 5], $attributes));
    }

    protected function category(string $name = 'Floral'): Category
    {
        return Category::factory()->create(['name' => $name]);
    }

    /** valid checkout fields (no price / total on purpose) */
    protected function checkoutData(array $extra = []): array
    {
        return array_merge([
            'full_name' => 'Juan Dela Cruz',
            'address' => '24 Rizal Street',
            'city' => 'Liloan, Cebu',
            'phone' => '0917 123 4567',
            'payment_method' => 'Cash on Delivery',
        ], $extra);
    }
}
