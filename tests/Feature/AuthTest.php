<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $o = []): Admin
    {
        return Admin::create($o + [
            'name' => 'Mehdi',
            'phone' => '+212652403812',
            'password' => 'secret123',
            'active' => true,
        ]);
    }

    public function test_login_returns_token_and_user(): void
    {
        $this->admin();

        $this->postJson('/api/auth/login', ['phone' => '0652-403812', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['accessToken', 'user' => ['id', 'name', 'phone', 'role']])
            ->assertJsonPath('user.role', 'admin');
    }

    public function test_wrong_password_is_401(): void
    {
        $this->admin();

        $this->postJson('/api/auth/login', ['phone' => '+212652403812', 'password' => 'nope'])
            ->assertStatus(401);
    }

    public function test_inactive_admin_is_403(): void
    {
        $this->admin(['active' => false]);

        $this->postJson('/api/auth/login', ['phone' => '+212652403812', 'password' => 'secret123'])
            ->assertStatus(403);
    }

    public function test_bad_phone_is_422(): void
    {
        $this->postJson('/api/auth/login', ['phone' => '123', 'password' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_me_and_logout(): void
    {
        $this->admin();

        $token = $this->postJson('/api/auth/login', ['phone' => '+212652403812', 'password' => 'secret123'])
            ->json('accessToken');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('name', 'Mehdi');

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}