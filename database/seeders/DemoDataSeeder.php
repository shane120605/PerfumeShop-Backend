<?php


namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CategorySeeder::class, ProductSeeder::class]);

        $password = (string) config('shop.demo_password') ?: Str::password(16, symbols: false);

        $customers = [
            'Juan Dela Cruz' => 'juan@shop.com',
            'Ana Santos' => 'ana@shop.com',
            'Maria Lopez' => 'maria@shop.com',
            'Carlo Reyes' => 'carlo@shop.com',
            'Liza Mendoza' => 'liza@shop.com',
        ];
        $users = [];
        foreach ($customers as $name => $email) {
            $users[$email] = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'role' => User::ROLE_CUSTOMER]
            );
        }

        // [order_number, customer email, date, status, [[product name, qty], ...], payment]
        $orders = [
            [12345, 'juan@shop.com', '2025-05-20', 'pending', [['Rose Éclat', 1], ['Vanilla Bloom', 1]], 'Cash on Delivery'],
            [12344, 'ana@shop.com', '2025-05-18', 'delivered', [['Noir Velvet', 1]], 'Cash on Delivery'],
            [12343, 'maria@shop.com', '2025-05-15', 'delivered', [['Jasmine Mist', 1], ['Vanilla Bloom', 1]], 'Cash on Delivery'],
            [12342, 'carlo@shop.com', '2025-05-12', 'cancelled', [['Amber Muse', 1]], 'Cash on Delivery'],
            [12341, 'liza@shop.com', '2025-05-09', 'delivered', [['Santal Royale', 1]], 'GCash / Online Payment'],
            [12340, 'ana@shop.com', '2025-05-06', 'delivered', [['White Musk', 2]], 'Cash on Delivery'],
            [12339, 'maria@shop.com', '2025-05-03', 'delivered', [['Citrus Aura', 3]], 'Cash on Delivery'],
            [12338, 'carlo@shop.com', '2025-05-28', 'pending', [['Oud Nocturne', 1]], 'GCash / Online Payment'],
            [12337, 'liza@shop.com', '2025-05-25', 'delivered', [['Peony Blush', 2]], 'Cash on Delivery'],
            [12336, 'juan@shop.com', '2025-05-23', 'delivered', [['Golden Honey', 1], ['Linen Breeze', 1]], 'Cash on Delivery'],
            [12335, 'ana@shop.com', '2025-05-30', 'pending', [['Rose Éclat', 2]], 'Cash on Delivery'],
            [12334, 'juan@shop.com', '2025-05-14', 'delivered', [['Santal Royale', 1], ['Citrus Aura', 1]], 'Cash on Delivery'],
        ];

        foreach ($orders as [$number, $email, $date, $status, $lines, $payment]) {
            if (Order::where('order_number', $number)->exists()) {
                continue;
            }

            $user = $users[$email];
            $subtotal = 0;
            $items = [];
            foreach ($lines as [$productName, $qty]) {
                $product = Product::where('name', $productName)->firstOrFail();
                $line = (float) $product->price * $qty;
                $subtotal += $line;
                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $qty,
                    'line_total' => number_format($line, 2, '.', ''),
                ];
            }

            $order = new Order;
            $order->forceFill([
                'order_number' => $number,
                'user_id' => $user->id,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'phone' => '0917 123 4567',
                'shipping_address' => '24 Rizal Street, Barangay Poblacion',
                'city' => 'Liloan, Cebu',
                'payment_method' => $payment,
                'status' => $status,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'shipping_fee' => '0.00',
                'total' => number_format($subtotal, 2, '.', ''),
                'created_at' => $date.' 10:00:00',
                'updated_at' => $date.' 10:00:00',
            ])->save();
            $order->items()->createMany($items);
        }

        $this->command?->info('Demo customers + orders created.');
        if (! config('shop.demo_password')) {
            $this->command?->warn("Demo customer password (shown once): {$password}");
        }
    }
}
