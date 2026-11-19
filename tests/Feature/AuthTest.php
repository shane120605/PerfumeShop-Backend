<?php

// Tests: registration, login, admin login, /me, logout

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Juan Dela Cruz',
            'email' => 'Juan@Example.com',
            'password' => 'a-strong-password',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('user.role', 'Customer');

        $this->assertDatabaseHas('users', ['email' => 'juan@example.com', 'role' => 'customer']);
        $this->assertNotSame('a-strong-password', User::first()->password); // stored hashed
    }

    public function test_registration_cannot_create_an_admin(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'a-strong-password',
            'role' => 'admin',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'sneaky@example.com', 'role' => 'customer']);
    }

    public function test_registration_is_validated(): void
    {
        $this->customer(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', ['name' => '', 'email' => 'bad', 'password' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->postJson('/api/auth/register', ['name' => 'X', 'email' => 'taken@example.com', 'password' => 'a-strong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_customer_can_login_and_use_the_token(): void
    {
        $user = $this->customer(['email' => 'ana@example.com']);

        $login = $this->postJson('/api/auth/login', ['identifier' => 'ana@example.com', 'password' => 'test-password-123'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->withHeader('Authorization', 'Bearer '.$login->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@example.com');
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $this->customer(['email' => 'ana@example.com']);

        $this->postJson('/api/auth/login', ['identifier' => 'ana@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identifier']);

        $this->postJson('/api/auth/login', ['identifier' => 'nobody@example.com', 'password' => 'whatever-123'])
            ->assertUnprocessable();
    }

    public function test_admin_must_use_the_admin_login(): void
    {
        $this->admin(['email' => 'boss@example.com']);

        $this->postJson('/api/auth/login', ['identifier' => 'boss@example.com', 'password' => 'test-password-123'])
            ->assertForbidden();

        $this->postJson('/api/auth/admin/login', ['identifier' => 'boss@example.com', 'password' => 'test-password-123'])
            ->assertOk()
            ->assertJsonPath('user.role', 'Admin');
    }

    public function test_customer_cannot_use_the_admin_login(): void
    {
        $this->customer(['email' => 'ana@example.com']);

        $this->postJson('/api/auth/admin/login', ['identifier' => 'ana@example.com', 'password' => 'test-password-123'])
            ->assertUnprocessable();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_logout_deletes_the_token(): void
    {
        $this->customer(['email' => 'ana@example.com']);

        $token = $this->postJson('/api/auth/login', ['identifier' => 'ana@example.com', 'password' => 'test-password-123'])
            ->json('token');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_api_url_returns_json_404(): void
    {
        $this->getJson('/api/does-not-exist')->assertNotFound()->assertJsonStructure(['message']);
    }
}
