<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $name, string $phone, string $role = 'admin'): Admin
    {
        $admin = Admin::create([
            'name' => $name,
            'phone' => $phone,
            'password' => 'secret123',
            'active' => true,
        ]);
        $admin->forceFill(['role' => $role])->save();

        return $admin;
    }

    private function body(Admin $a, string $password): array
    {
        return [
            'name' => $a->name,
            'phone' => $a->phone,
            'active' => true,
            'password' => $password,
        ];
    }

    public function test_admin_cannot_change_another_admins_password(): void
    {
        $me = $this->account('Mehdi', '+212652403812');
        $other = $this->account('Bader', '+212619109600');
        Sanctum::actingAs($me);

        $this->putJson("/api/admin/admins/{$other->id}", $this->body($other, 'hacked12345'))
            ->assertForbidden();

        $this->assertTrue(Hash::check('secret123', $other->fresh()->password));
    }

    public function test_admin_can_change_their_own_password(): void
    {
        $me = $this->account('Mehdi', '+212652403812');
        $this->account('Bader', '+212619109600');
        Sanctum::actingAs($me);

        $this->putJson("/api/admin/admins/{$me->id}", $this->body($me, 'newpass1234'))
            ->assertOk();

        $this->assertTrue(Hash::check('newpass1234', $me->fresh()->password));
    }

    public function test_admin_can_still_edit_another_admin_without_a_password(): void
    {
        $me = $this->account('Mehdi', '+212652403812');
        $other = $this->account('Bader', '+212619109600');
        Sanctum::actingAs($me);

        $this->putJson("/api/admin/admins/{$other->id}", [
            'name' => 'Bader K',
            'phone' => $other->phone,
            'active' => true,
        ])->assertOk()->assertJsonPath('name', 'Bader K');
    }

    public function test_super_admin_can_reset_an_admins_password(): void
    {
        $super = $this->account('Ahmed', '+212707641333', 'super_admin');
        $other = $this->account('Bader', '+212619109600');
        Sanctum::actingAs($super);

        $this->putJson("/api/admin/admins/{$other->id}", $this->body($other, 'reset12345'))
            ->assertOk();

        $this->assertTrue(Hash::check('reset12345', $other->fresh()->password));
    }
}