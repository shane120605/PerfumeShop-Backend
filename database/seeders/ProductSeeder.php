<?php


namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['Rose Éclat', 'Floral', 2450, 10, 1, 'A radiant bouquet of Bulgarian rose and peony softened by white musk. Elegant, feminine and long-lasting, perfect for day to evening wear.', ['Bergamot', 'Rose, Peony', 'White Musk']],
            ['Noir Velvet', 'Woody', 3200, 8, 2, 'A deep, smoky blend of cedar and black amber with a velvety finish. Made for confident evenings.', ['Black Pepper', 'Cedarwood', 'Amber']],
            ['Amber Muse', 'Oriental', 2750, 5, 3, 'Warm golden amber wrapped in soft spices and vanilla. A cozy, glowing signature scent.', ['Cardamom', 'Amber', 'Vanilla']],
            ['Vanilla Bloom', 'Gourmand', 1980, 15, 4, 'Creamy Madagascar vanilla with a hint of orange blossom. Sweet, soft and comforting.', ['Orange Blossom', 'Vanilla', 'Tonka Bean']],
            ['Jasmine Mist', 'Floral', 1650, 12, 1, 'Light jasmine petals in a fresh morning mist. Clean, airy and easy to wear every day.', ['Green Tea', 'Jasmine', 'Soft Musk']],
            ['Santal Royale', 'Woody', 3450, 6, 2, 'Rich sandalwood with creamy coconut milk and smooth leather. A refined, luxurious classic.', ['Saffron', 'Sandalwood', 'Leather']],
            ['Citrus Aura', 'Fresh', 1450, 20, 3, 'Sparkling lemon, grapefruit and neroli. A bright pick-me-up for warm days.', ['Lemon, Grapefruit', 'Neroli', 'Cedar']],
            ['White Musk', 'Musky', 2150, 9, 4, 'Clean, powdery white musk with a touch of lily. Soft like fresh linen on skin.', ['Lily', 'White Musk', 'Cashmeran']],
            ['Peony Blush', 'Floral', 2250, 11, 1, 'Juicy pink peony with lychee and a gentle rosy glow. Playful and romantic.', ['Lychee', 'Peony', 'Blonde Woods']],
            ['Oud Nocturne', 'Oriental', 3800, 4, 2, 'Dark oud, incense and a trail of smoked rose. Bold, mysterious and unforgettable.', ['Incense', 'Oud, Rose', 'Patchouli']],
            ['Golden Honey', 'Gourmand', 2050, 14, 3, 'Warm honey drizzled over almond and sweet musk. Golden, glowing and cozy.', ['Almond', 'Honey', 'Sweet Musk']],
            ['Linen Breeze', 'Fresh', 1550, 18, 4, 'Crisp sea air and clean linen with a hint of sage. Light and refreshing.', ['Sea Salt', 'Sage', 'Driftwood']],
        ];

        foreach ($products as [$name, $category, $price, $stock, $image, $description, $notes]) {
            $categoryId = Category::where('name', $category)->value('id');

            // updateOrCreate by name: running the seeder twice does not duplicate products
            Product::updateOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categoryId,
                    'description' => $description,
                    'price' => $price,
                    'stock' => $stock,
                    'size' => '50 ml',
                    'image_number' => $image,
                    'notes_top' => $notes[0],
                    'notes_heart' => $notes[1],
                    'notes_base' => $notes[2],
                ]
            );
        }
    }
}
