<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create super admin
        AdminUser::create([
            'username' => 'admin',
            'email' => 'admin@pamdes.id',
            'password' => Hash::make('password'),
            'name' => 'PAMDes Administrator',
            'role' => 'admin',
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
            AdminUser::create([
                'username' => 'admin_' . strtolower($village['name']),
                'email' => 'admin@pamdes.' . strtolower($village['name']) . '.id',
                'password' => Hash::make('password'),
                'name' => 'Admin PAMDes ' . $village['name'],
                'role' => 'village_admin',
                'contact_info' => '+62 813-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                'village_id' => $village['id'],
                'is_active' => true,
            ]);

            // Create cashier for each village
            AdminUser::create([
                'username' => 'kasir_' . strtolower($village['name']),
                'email' => 'kasir@pamdes.' . strtolower($village['name']) . '.id',
                'password' => Hash::make('password'),
                'name' => 'Kasir PAMDes ' . $village['name'],
                'role' => 'cashier',
                'contact_info' => '+62 814-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                'village_id' => $village['id'],
                'is_active' => true,
            ]);
        }
    }
}
