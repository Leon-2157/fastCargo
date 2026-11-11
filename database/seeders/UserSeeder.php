<?php

namespace Database\Seeders;

use App\Modules\Shared\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Password untuk semua seed user: "password"
        $password = Hash::make('password');

        $users = [
            [
                'id' => '019445a1-0001-7000-8000-000000000001',
                'name' => 'Super Admin',
                'username' => 'admin',
                'email' => 'admin@fastcargo.internal',
                'phone' => '+62811000001',
                'password' => $password,
                'user_type' => 'ADMIN_USER',
                'admin_role' => 'SUPER_ADMIN',
                'status' => 'ACTIVE',
                'kyc_aml_status' => 'VERIFIED',
                'timezone' => 'Asia/Jakarta',
            ],
            [
                'id' => '019445a1-0002-7000-8000-000000000002',
                'name' => 'Head Operator',
                'username' => 'operator',
                'email' => 'operator@fastcargo.com',
                'phone' => '+62811000002',
                'password' => $password,
                'user_type' => 'OPERATOR_USER',
                'admin_role' => null,
                'status' => 'ACTIVE',
                'kyc_aml_status' => 'VERIFIED',
                'timezone' => 'Asia/Jakarta',
            ],
            [
                'id' => '019445a1-0003-7000-8000-000000000003',
                'name' => 'Demo Customer',
                'username' => 'customer',
                'email' => 'customer@example.com',
                'phone' => '+62811000003',
                'password' => $password,
                'user_type' => 'RETAIL_CUSTOMER',
                'admin_role' => null,
                'status' => 'ACTIVE',
                'kyc_aml_status' => 'VERIFIED',
                'email_verified_at' => now(),
                'timezone' => 'Asia/Jakarta',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(['email' => $userData['email']], $userData);
        }
    }
}
