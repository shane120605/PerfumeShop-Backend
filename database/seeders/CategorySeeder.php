<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Floral' => 'Soft, romantic scents built around flowers.',
            'Woody' => 'Warm, deep scents of cedar, sandalwood and oud.',
            'Oriental' => 'Rich amber, spice and incense fragrances.',
            'Fresh' => 'Light, clean citrus and marine scents.',
            'Gourmand' => 'Sweet, edible notes like vanilla and honey.',
            'Musky' => 'Soft, powdery, skin-like musk scents.',
        ];

        foreach ($categories as $name => $description) {
            Category::updateOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name), 'description' => $description]
            );
        }
    }
}
