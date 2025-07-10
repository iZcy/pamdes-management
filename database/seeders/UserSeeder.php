<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create super admin (can access all villages)
        User::create([
            'name' => 'PAMDes Super Administrator',
            'email' => 'admin@pamdes.id',
            'password' => Hash::make('password'),
            'contact_info' => '+62 812-3456-7890',
            'village_id' => null, // Super admin can access all villages
            'is_active' => true,
        ]);

        // Create village-specific admins for each village
        $villages = [
            ['id' => 'bayan-village-id', 'name' => 'Bayan'],
            ['id' => 'senaru-village-id', 'name' => 'Senaru'],
            ['id' => 'pemenang-village-id', 'name' => 'Pemenang'],
        ];

        foreach ($villages as $village) {
            User::create([
                'name' => 'Admin PAMDes ' . $village['name'],
                'email' => 'admin@pamdes.' . strtolower($village['name']) . '.id',
                'password' => Hash::make('password'),
                'contact_info' => '+62 813-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                'village_id' => $village['id'],
                'is_active' => true,
            ]);
        }
    }
}
