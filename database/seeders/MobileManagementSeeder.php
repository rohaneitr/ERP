<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MobileManagementSeeder extends Seeder
{
    public function run(): void
    {
        // ── Subscription Plans ──────────────────────────────────────────────
        $plans = [
            [
                'name'         => 'Free Trial',
                'slug'         => 'trial',
                'description'  => '14-day free trial with 1 device',
                'price'        => 0.00,
                'currency'     => 'USD',
                'duration_days'=> 14,
                'max_devices'  => 1,
                'max_users'    => 1,
                'max_locations'=> 1,
                'features'     => json_encode(['pos', 'inventory']),
                'is_active'    => true,
                'sort_order'   => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'name'         => 'Basic',
                'slug'         => 'basic',
                'description'  => 'Single device, monthly',
                'price'        => 9.99,
                'currency'     => 'USD',
                'duration_days'=> 30,
                'max_devices'  => 1,
                'max_users'    => 3,
                'max_locations'=> 1,
                'features'     => json_encode(['pos', 'inventory', 'reports']),
                'is_active'    => true,
                'sort_order'   => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'name'         => 'Professional',
                'slug'         => 'pro',
                'description'  => 'Up to 5 devices, advanced features',
                'price'        => 29.99,
                'currency'     => 'USD',
                'duration_days'=> 30,
                'max_devices'  => 5,
                'max_users'    => 20,
                'max_locations'=> 5,
                'features'     => json_encode(['pos', 'inventory', 'reports', 'sync', 'contacts']),
                'is_active'    => true,
                'sort_order'   => 2,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'name'         => 'Enterprise',
                'slug'         => 'enterprise',
                'description'  => 'Unlimited devices, priority support',
                'price'        => 99.99,
                'currency'     => 'USD',
                'duration_days'=> 30,
                'max_devices'  => 99,
                'max_users'    => 999,
                'max_locations'=> 99,
                'features'     => json_encode(['pos', 'inventory', 'reports', 'sync', 'contacts', 'api', 'priority_support']),
                'is_active'    => true,
                'sort_order'   => 3,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('subscription_plans')->updateOrInsert(['slug' => $plan['slug']], $plan);
        }

        // ── Spatie Permissions ──────────────────────────────────────────────
        $permissions = [
            'manage_mobile_users',
            'manage_subscriptions',
            'view_audit_logs',
            'manage_devices',
            'approve_registrations',
            'reset_user_passwords',
            'force_user_logout',
            'manage_subscription_plans',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Assign all new permissions to every Admin role (Admin#1, Admin#2, etc.)
        Role::where('name', 'like', 'Admin#%')->each(function (Role $role) use ($permissions) {
            $role->givePermissionTo($permissions);
        });

        $this->command->info('MobileManagementSeeder: plans and permissions seeded.');
    }
}
