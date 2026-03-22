<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure the site_administrator role exists.
        Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);

        // System user — no password, is_system = true. Never surfaces in UI.
        User::withoutEvents(function () {
            User::firstOrCreate(
                ['email' => 'system@ares.internal'],
                [
                    'username' => 'system',
                    'name' => 'System',
                    'password' => null,
                    'is_system' => true,
                    'email_verified_at' => now(),
                ]
            );
        });

        // Site Admin — password from env. No hardcoded default.
        $adminPassword = env('ADMIN_PASSWORD');
        abort_if(empty($adminPassword), 1, 'ADMIN_PASSWORD env variable must be set before seeding.');

        $admin = User::firstOrCreate(
            ['email' => 'admin@sheql.com'],
            [
                'username' => 'admin',
                'name' => 'Site Administrator',
                'password' => $adminPassword,
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles(['site_administrator']);
    }
}
