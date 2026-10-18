<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Mehdi', 'phone' => '+212652403812', 'env' => 'ADMIN_MEHDI_PASSWORD', 'role' => Admin::ROLE_ADMIN],
            ['name' => 'Bader', 'phone' => '+212619109600', 'env' => 'ADMIN_BADER_PASSWORD', 'role' => Admin::ROLE_ADMIN],
            ['name' => 'Ahmed', 'phone' => '+212707641333', 'env' => 'SUPER_ADMIN_PASSWORD', 'role' => Admin::ROLE_SUPER],
        ];

        foreach ($accounts as $a) {
            $password = env($a['env']);

            if (! $password) {
                throw new \RuntimeException("Set {$a['env']} in .env before seeding.");
            }

            $admin = Admin::firstOrNew(['phone' => $a['phone']]);
            $admin->fill(['name' => $a['name'], 'password' => $password, 'active' => true]);
            // "role" is not fillable: it can only be set here
            $admin->forceFill(['role' => $a['role']]);
            $admin->save();
        }
    }
}