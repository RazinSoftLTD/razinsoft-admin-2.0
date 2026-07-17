<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin (Filament)
        User::updateOrCreate(
            ['email' => 'admin@razinsoft.com'],
            ['name' => 'RazinSoft Admin', 'role' => 'admin', 'password' => Hash::make('password')],
        );

        // Demo customer
        User::updateOrCreate(
            ['email' => 'customer@razinsoft.com'],
            ['name' => 'John Doe', 'role' => 'customer', 'phone' => '+8801711257408', 'password' => Hash::make('password')],
        );

        // Coupons
        Coupon::updateOrCreate(['code' => 'RAZIN10'], ['type' => 'percent', 'value' => 10, 'is_active' => true]);
        Coupon::updateOrCreate(['code' => 'SAVE20'], ['type' => 'percent', 'value' => 20, 'is_active' => true]);

        $this->call(ProductSeeder::class);
        $this->call(EmployeeRoleSeeder::class);
    }
}
