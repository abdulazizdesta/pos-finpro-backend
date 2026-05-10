<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $business = Business::create([
            'name' => 'Retail',
            'code' => 'RTL',
        ]);

        $outlet = Outlet::create([
            'business_id' => $business->id,
            'name' => 'Retail First outlet',
            'code' => 'O-RTL',
        ]);

        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@pos.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::SUPERADMIN,
            'is_active' => true,
            'business_id' => null,
            'outlet_id' => null,
        ]);

        User::create([
            'name' => 'Owner',
            'email' => 'own@gmail.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::OWNER,
            'is_active' => true,
            'business_id' => $business->id,
            'outlet_id' => null,
        ]);

        User::create([
            'name' => 'Manager',
            'email' => 'manager@gmail.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
        ]);

        User::create([
            'name' => 'Cashier',
            'email' => 'cashier@gmail.com',
            'password' => Hash::make('password123'),
            'pin'         => Hash::make('123456'),
            'role' => UserRole::CASHIER,
            'is_active' => true,
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
        ]);
    }
}
