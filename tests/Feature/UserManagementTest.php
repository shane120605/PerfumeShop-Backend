<?php

// Tests: admin user/customer management, dashboard numbers, sales report, first-admin command

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_list_and_delete_users(): void
    {
        Sanctum::actingAs($this->admin());

        $id = $this->postJson('/api/admin/users', [
            'name' => 'Maria Lopez', 'email' => 'maria@example.com', 'role' => 'Customer', 'password' => 'a-strong-password',
        ])->assertCreated()->assertJsonMissingPath('data.password')->json('data.id');

        $this->assertSame('customer', User::find($id)->role);

        $this->putJson('/api/admin/users/'.$id, ['name' => 'Maria L.', 'role' => 'Admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'Admin');

        $this->getJson('/api/admin/users?role=customer')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/users?search=maria')->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson('/api/admin/users/'.$id)->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $id]);
    }

    public function test_blank_password_on_update_keeps_the_old_password(): void
    {
        Sanctum::actingAs($this->admin());
        $user = $this->customer();
        $oldHash = $user->password;

        $this->putJson('/api/admin/users/'.$user->id, ['name' => 'Renamed', 'password' => ''])->assertOk();

        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_user_validation(): void
    {
        Sanctum::actingAs($this->admin());
        $this->customer(['email' => 'taken@example.com']);

        $this->postJson('/api/admin/users', ['name' => '', 'email' => 'taken@example.com', 'role' => 'superuser', 'password' => '1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'role', 'password']);
    }

    public function test_admin_cannot_delete_or_demote_themselves(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/admin/users/'.$admin->id)->assertUnprocessable();
        $this->putJson('/api/admin/users/'.$admin->id, ['role' => 'Customer'])->assertUnprocessable();

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_an_admin_can_delete_another_admin_when_more_than_one_exists(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/admin/users/'.$other->id)->assertOk(); // two admins exist -> allowed
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_dashboard_counts_and_ignores_cancelled_orders_in_sales(): void
    {
        $product = $this->product(['price' => 100, 'stock' => 50]);
        $customer = $this->customer();
        $service = app(OrderService::class);
        $shipping = ['full_name' => 'X', 'address' => 'A', 'city' => 'C', 'phone' => '0917 123 4567'];

        $service->place($customer, $shipping, 'Cash on Delivery', [$product->id => 2]);             // 200
        $cancelled = $service->place($customer, $shipping, 'Cash on Delivery', [$product->id => 5]); // 500 (cancelled)
        $service->changeStatus($cancelled, Order::STATUS_CANCELLED);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.total_products', 1)
            ->assertJsonPath('data.pending_orders', 1);

        $this->assertEquals(200, $this->getJson('/api/admin/dashboard')->json('data.total_sales'));
    }

    public function test_sales_report_groups_orders_and_validates_dates(): void
    {
        $product = $this->product(['price' => 100, 'stock' => 100]);
        $customer = $this->customer();
        $service = app(OrderService::class);
        $shipping = ['full_name' => 'X', 'address' => 'A', 'city' => 'C', 'phone' => '0917 123 4567'];

        foreach (['2025-05-03' => 1, '2025-05-10' => 2, '2025-06-20' => 4] as $date => $qty) {
            $order = $service->place($customer, $shipping, 'Cash on Delivery', [$product->id => $qty]);
            $order->forceFill(['created_at' => $date.' 10:00:00'])->save();
        }

        Sanctum::actingAs($this->admin());

        $report = $this->getJson('/api/admin/reports/sales?from=2025-05-01&to=2025-05-31')->assertOk();
        $this->assertSame('week', $report->json('data.unit'));
        $this->assertEquals(300, $report->json('data.total_sales'));  // 100 + 200 (June order excluded)
        $this->assertSame(2, $report->json('data.total_orders'));
        $this->assertEquals(150, $report->json('data.average_order'));
        $this->assertEquals(100, $report->json('data.buckets.0.value')); // May 1-7
        $this->assertEquals(200, $report->json('data.buckets.1.value')); // May 8-14

        $long = $this->getJson('/api/admin/reports/sales?from=2025-01-01&to=2025-12-31')->assertOk();
        $this->assertSame('month', $long->json('data.unit'));
        $this->assertEquals(700, $long->json('data.total_sales'));

        $this->getJson('/api/admin/reports/sales?from=2025-06-01&to=2025-05-01')->assertUnprocessable();
    }

    public function test_create_admin_command_creates_an_admin_and_refuses_weak_passwords(): void
    {
        $this->artisan('shop:create-admin', ['--name' => 'Boss', '--email' => 'boss@example.com', '--password' => 'short'])
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);

        $this->artisan('shop:create-admin', ['--name' => 'Boss', '--email' => 'boss@example.com', '--password' => 'a-strong-password'])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'boss@example.com', 'role' => 'admin']);
    }
}
