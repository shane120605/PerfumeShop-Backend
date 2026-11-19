<?php

// Tests: checkout (server-side prices, stock, transaction), order history, cancel, admin status updates

namespace Tests\Feature;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_is_calculated_by_the_server_and_submitted_prices_are_ignored(): void
    {
        $user = $this->customer();
        $product = $this->product(['price' => 100, 'stock' => 5]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', $this->checkoutData([
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 1]], // fake price
            'total' => 1,                                                              // fake total
        ]))->assertCreated();

        $this->assertEquals(200, $response->json('data.total'));
        $order = Order::firstOrFail();
        $this->assertEquals('200.00', $order->total);
        $this->assertEquals('100.00', $order->items->first()->unit_price);
        $this->assertSame('pending', $order->status);
        $this->assertSame($user->id, $order->user_id);
    }

    public function test_stock_is_reduced_after_an_order(): void
    {
        Sanctum::actingAs($this->customer());
        $product = $this->product(['stock' => 5]);

        $this->postJson('/api/orders', $this->checkoutData(['items' => [['product_id' => $product->id, 'quantity' => 2]]]))
            ->assertCreated();

        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_order_is_refused_when_stock_is_not_enough(): void
    {
        Sanctum::actingAs($this->customer());
        $product = $this->product(['stock' => 1]);

        $this->postJson('/api/orders', $this->checkoutData(['items' => [['product_id' => $product->id, 'quantity' => 2]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_nothing_is_saved_when_one_item_fails(): void
    {
        Sanctum::actingAs($this->customer());
        $enough = $this->product(['stock' => 10]);
        $notEnough = $this->product(['stock' => 1]);

        $this->postJson('/api/orders', $this->checkoutData(['items' => [
            ['product_id' => $enough->id, 'quantity' => 3],
            ['product_id' => $notEnough->id, 'quantity' => 5],
        ]]))->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(10, $enough->fresh()->stock); // untouched
    }

    public function test_checkout_validation(): void
    {
        Sanctum::actingAs($this->customer());
        $product = $this->product();

        $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'address', 'city', 'phone', 'payment_method']);

        $this->postJson('/api/orders', $this->checkoutData([
            'payment_method' => 'Bitcoin',
            'items' => [['product_id' => 99999, 'quantity' => 1]],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['payment_method', 'items.0.product_id']);

        $this->postJson('/api/orders', $this->checkoutData(['items' => [['product_id' => $product->id, 'quantity' => 0]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_server_cart_is_used_when_items_are_not_sent_and_is_emptied_afterwards(): void
    {
        Sanctum::actingAs($this->customer());
        $product = $this->product(['price' => 250, 'stock' => 5]);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertCreated();
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertJsonPath('data.count', 3)
            ->assertJsonPath('data.subtotal', 750.0);

        $this->postJson('/api/orders', $this->checkoutData())->assertCreated();

        $this->assertEquals('750.00', Order::firstOrFail()->total);
        $this->getJson('/api/cart')->assertJsonPath('data.count', 0);
    }

    public function test_empty_cart_cannot_check_out(): void
    {
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/orders', $this->checkoutData())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_cart_cannot_exceed_stock(): void
    {
        Sanctum::actingAs($this->customer());
        $product = $this->product(['stock' => 2]);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_customer_only_sees_their_own_orders(): void
    {
        $mine = $this->customer();
        $other = $this->customer();
        $product = $this->product(['stock' => 20]);
        $service = app(OrderService::class);
        $shipping = ['full_name' => 'X', 'address' => 'A', 'city' => 'C', 'phone' => '0917 123 4567'];

        $myOrder = $service->place($mine, $shipping, 'Cash on Delivery', [$product->id => 1]);
        $theirOrder = $service->place($other, $shipping, 'Cash on Delivery', [$product->id => 1]);

        Sanctum::actingAs($mine);

        $this->getJson('/api/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.no', $myOrder->order_number);
        $this->getJson('/api/orders/'.$myOrder->order_number)->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson('/api/orders/'.$theirOrder->order_number)->assertNotFound();
        $this->postJson('/api/orders/'.$theirOrder->order_number.'/cancel')->assertNotFound();
    }

    public function test_order_numbers_are_unique_and_increasing(): void
    {
        $user = $this->customer();
        $product = $this->product(['stock' => 20]);
        $service = app(OrderService::class);
        $shipping = ['full_name' => 'X', 'address' => 'A', 'city' => 'C', 'phone' => '0917 123 4567'];

        $a = $service->place($user, $shipping, 'Cash on Delivery', [$product->id => 1]);
        $b = $service->place($user, $shipping, 'Cash on Delivery', [$product->id => 1]);

        $this->assertSame($a->order_number + 1, $b->order_number);
    }

    public function test_customer_can_cancel_a_pending_order_and_stock_comes_back(): void
    {
        $user = $this->customer();
        $product = $this->product(['stock' => 5]);
        Sanctum::actingAs($user);

        $no = $this->postJson('/api/orders', $this->checkoutData(['items' => [['product_id' => $product->id, 'quantity' => 2]]]))
            ->json('data.no');
        $this->assertSame(3, $product->fresh()->stock);

        $this->postJson("/api/orders/{$no}/cancel")->assertOk()->assertJsonPath('data.status', 'Cancelled');

        $this->assertSame(5, $product->fresh()->stock);
        $this->postJson("/api/orders/{$no}/cancel")->assertOk(); // cancelling twice does not return stock twice
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_admin_can_update_order_status(): void
    {
        $order = $this->orderFor($this->customer());
        Sanctum::actingAs($this->admin());

        $this->patchJson('/api/admin/orders/'.$order->order_number.'/status', ['status' => 'Delivered'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Delivered');

        $this->assertSame('delivered', $order->fresh()->status);

        // a delivered order is final
        $this->patchJson('/api/admin/orders/'.$order->order_number.'/status', ['status' => 'Cancelled'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_admin_cancelling_an_order_returns_the_stock(): void
    {
        $product = $this->product(['stock' => 5]);
        $order = $this->orderFor($this->customer(), $product, 3);
        $this->assertSame(2, $product->fresh()->stock);
        Sanctum::actingAs($this->admin());

        $this->patchJson('/api/admin/orders/'.$order->order_number.'/status', ['status' => 'cancelled'])->assertOk();

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $order = $this->orderFor($this->customer());
        Sanctum::actingAs($this->admin());

        $this->patchJson('/api/admin/orders/'.$order->order_number.'/status', ['status' => 'Teleported'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_customer_cannot_change_status_or_see_admin_orders(): void
    {
        $order = $this->orderFor($this->customer());
        Sanctum::actingAs($this->customer());

        $this->patchJson('/api/admin/orders/'.$order->order_number.'/status', ['status' => 'Delivered'])->assertForbidden();
        $this->getJson('/api/admin/orders')->assertForbidden();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_admin_can_list_filter_and_view_orders(): void
    {
        $pending = $this->orderFor($this->customer(['name' => 'Ana Santos']));
        $delivered = $this->orderFor($this->customer(['name' => 'Carlo Reyes']));
        app(OrderService::class)->changeStatus($delivered, 'delivered');
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/orders')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/orders?status=Pending')->assertJsonCount(1, 'data')->assertJsonPath('data.0.no', $pending->order_number);
        $this->getJson('/api/admin/orders?search=carlo')->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/orders/'.$pending->order_number)->assertOk()->assertJsonStructure(['data' => ['no', 'customer', 'items']]);
    }

    private function orderFor($user, $product = null, int $qty = 1): Order
    {
        $product ??= $this->product(['stock' => 20]);

        return app(OrderService::class)->place(
            $user,
            ['full_name' => $user->name, 'address' => 'A', 'city' => 'C', 'phone' => '0917 123 4567'],
            'Cash on Delivery',
            [$product->id => $qty]
        );
    }
}
