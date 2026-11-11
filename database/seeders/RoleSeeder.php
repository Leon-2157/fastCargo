<?php

namespace Database\Seeders;

use App\Modules\Shared\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Spatie roles per guard
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'finance_admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'dispatch_admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'compliance_admin', 'guard_name' => 'admin']);

        Role::firstOrCreate(['name' => 'operator_domestic', 'guard_name' => 'operator']);
        Role::firstOrCreate(['name' => 'operator_international', 'guard_name' => 'operator']);

        Role::firstOrCreate(['name' => 'retail_customer', 'guard_name' => 'customer']);
        Role::firstOrCreate(['name' => 'corporate_user', 'guard_name' => 'customer']);

        // Assign seed users
        $admin = User::where('email', 'admin@fastcargo.internal')->first();
        if ($admin) {
            $admin->assignRole(Role::findByName('super_admin', 'admin'));
        }

        $operator = User::where('email', 'operator@fastcargo.com')->first();
        if ($operator) {
            $operator->assignRole(Role::findByName('operator_domestic', 'operator'));
        }

        $customer = User::where('email', 'customer@example.com')->first();
        if ($customer) {
            $customer->assignRole(Role::findByName('retail_customer', 'customer'));
        }
    }
}
