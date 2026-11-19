<?php

// Tests: who may call which endpoints (guest / customer / admin)

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_rejected_on_protected_routes(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/admin/users')->assertUnauthorized();
        $this->postJson('/api/admin/products', [])->assertUnauthorized();
        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson('/api/cart')->assertUnauthorized();
        $this->postJson('/api/orders', [])->assertUnauthorized();
    }

    public function test_customers_cannot_use_admin_routes(): void
    {
        Sanctum::actingAs($this->customer());
        $product = $this->product();

        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->getJson('/api/admin/orders')->assertForbidden();
        $this->getJson('/api/admin/reports/sales')->assertForbidden();
        $this->postJson('/api/admin/products', [])->assertForbidden();
        $this->deleteJson('/api/admin/products/'.$product->id)->assertForbidden();
        $this->postJson('/api/admin/categories', ['name' => 'Hack'])->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_admins_cannot_use_customer_shopping_routes(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/cart')->assertForbidden();
        $this->getJson('/api/orders')->assertForbidden();
    }

    public function test_admin_can_open_the_dashboard(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['total_products', 'total_orders', 'total_users', 'total_sales', 'recent_orders']]);
    }
}
