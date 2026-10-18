<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'admin', string $phone = '+212652403812'): Admin
    {
        $admin = Admin::create([
            'name' => $role === 'super_admin' ? 'hmed' : 'Mehdi',
            'phone' => $phone,
            'password' => 'secret123',
            'active' => true,
        ]);
        $admin->forceFill(['role' => $role])->save();

        return $admin;
    }

    public function test_regular_admin_cannot_open_the_developer_dashboard(): void
    {
        Sanctum::actingAs($this->account());

        $this->getJson('/api/admin/system/overview')->assertForbidden();
        $this->postJson('/api/admin/system/prune-images')->assertForbidden();
        $this->deleteJson('/api/admin/system/errors')->assertForbidden();
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/admin/system/overview')->assertUnauthorized();
    }

    public function test_super_admin_gets_the_overview(): void
    {
        Sanctum::actingAs($this->account('super_admin', '+212707641333'));

        $this->getJson('/api/admin/system/overview?hours=24')
            ->assertOk()
            ->assertJsonStructure([
                'health', 'problems', 'traffic' => ['series', 'topRoutes'],
                'security', 'errors', 'storage', 'business', 'team',
            ]);
    }

    public function test_super_admin_is_invisible_in_the_shop_team(): void
    {
        $this->account('super_admin', '+212707641333');
        $regular = $this->account('admin', '+212652403812');
        Sanctum::actingAs($regular);

        $this->getJson('/api/admin/admins')->assertOk()->assertJsonCount(1);

        $super = Admin::where('phone', '+212707641333')->first();
        $this->deleteJson("/api/admin/admins/{$super->id}")->assertNotFound();
    }

    public function test_login_returns_the_super_admin_role(): void
    {
        $this->account('super_admin', '+212707641333');

        $this->postJson('/api/auth/login', ['phone' => '07 07 64 13 33', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.role', 'super_admin');
    }

    public function test_api_requests_are_logged(): void
    {
        $this->getJson('/api/categories')->assertOk();

        $this->assertDatabaseHas('request_logs', ['route' => 'api/categories', 'status' => 200]);
    }
}