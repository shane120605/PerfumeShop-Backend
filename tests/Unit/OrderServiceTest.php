<?php

// Unit-style tests of the money math (uses the database, so it extends the Laravel TestCase)

namespace Tests\Unit;

use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_use_exact_cents(): void
    {
        $user = $this->customer();
        $a = $this->product(['price' => 19.99, 'stock' => 10]);
        $b = $this->product(['price' => 0.10, 'stock' => 10]);

        $order = app(OrderService::class)->place(
            $user,
            ['full_name' => 'X', 'address' => 'A', 'city' => 'C', 'phone' => '0917 123 4567'],
            'Cash on Delivery',
            [$a->id => 3, $b->id => 3]
        );

        $this->assertSame('60.27', number_format((float) $order->subtotal, 2, '.', '')); // 59.97 + 0.30
        $this->assertSame('60.27', number_format((float) $order->total, 2, '.', ''));
        $this->assertCount(2, $order->items);
    }

    public function test_order_keeps_the_old_price_after_the_product_changes(): void
    {
        $user = $this->customer();
        $product = $this->product(['price' => 100, 'stock' => 10]);

        $order = app(OrderService::class)->place(
            $user,
            ['full_name' => 'X', 'address' => 'A', 'city' => 'C', 'phone' => '0917 123 4567'],
            'Cash on Delivery',
            [$product->id => 1]
        );

        $product->update(['price' => 999]);
        $product->delete();

        $item = $order->fresh()->items->first();
        $this->assertEquals('100.00', $item->unit_price);
        $this->assertNull($item->product_id);
        $this->assertSame($product->name, $item->product_name);
    }
}
