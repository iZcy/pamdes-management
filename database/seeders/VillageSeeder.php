<?php
// database/seeders/VillageSeeder.php - Updated for independent system

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Village;
use Illuminate\Support\Str;

class VillageSeeder extends Seeder
{
    public function run(): void
    {
        $villages = [
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Bayan',
                'slug' => 'bayan',
                'description' => 'Desa Bayan, Kecamatan Bayan',
                'domain' => null,
                'latitude' => -8.3469,
                'longitude' => 116.3186,
                'phone_number' => '+62 370 123456',
                'email' => 'desa@bayan.local',
                'address' => 'Jl. Raya Bayan, Kecamatan Bayan, Lombok Utara',
                'image_url' => null,
                'settings' => [
                    'timezone' => 'Asia/Makassar',
                    'currency' => 'IDR',
                    'language' => 'id',
                ],
                'is_active' => true,
                'established_at' => now()->subYears(50),
                'pamdes_settings' => [
                    'default_admin_fee' => 5000,
                    'default_maintenance_fee' => 2000,
                    'auto_generate_bills' => true,
                    'overdue_threshold_days' => 30,
                    'allow_partial_payments' => false,
                    'late_fee_enabled' => false,
                ],
                'sync_enabled' => false, // Independent system
            ],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Senaru',
                'slug' => 'senaru',
                'description' => 'Desa Senaru, Kecamatan Bayan',
                'domain' => null,
                'latitude' => -8.3333,
                'longitude' => 116.4167,
                'phone_number' => '+62 370 123457',
                'email' => 'desa@senaru.local',
                'address' => 'Jl. Raya Senaru, Kecamatan Bayan, Lombok Utara',
                'image_url' => null,
                'settings' => [
                    'timezone' => 'Asia/Makassar',
                    'currency' => 'IDR',
                    'language' => 'id',
                ],
                'is_active' => true,
                'established_at' => now()->subYears(45),
                'pamdes_settings' => [
                    'default_admin_fee' => 3000,
                    'default_maintenance_fee' => 2000,
                    'auto_generate_bills' => true,
                    'overdue_threshold_days' => 45,
                    'allow_partial_payments' => true,
                    'late_fee_enabled' => false,
                ],
                'sync_enabled' => false, // Independent system
            ],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Pemenang',
                'slug' => 'pemenang',
                'description' => 'Desa Pemenang, Kecamatan Pemenang',
                'domain' => null,
                'latitude' => -8.3700,
                'longitude' => 116.0900,
                'phone_number' => '+62 370 123458',
                'email' => 'desa@pemenang.local',
                'address' => 'Jl. Raya Pemenang, Kecamatan Pemenang, Lombok Utara',
                'image_url' => null,
                'settings' => [
                    'timezone' => 'Asia/Makassar',
                    'currency' => 'IDR',
                    'language' => 'id',
                ],
                'is_active' => true,
                'established_at' => now()->subYears(40),
                'pamdes_settings' => [
                    'default_admin_fee' => 4000,
                    'default_maintenance_fee' => 1500,
                    'auto_generate_bills' => false,
                    'overdue_threshold_days' => 60,
                    'allow_partial_payments' => true,
                    'late_fee_enabled' => true,
                    'late_fee_amount' => 1000,
                ],
                'sync_enabled' => false, // Independent system
            ],
        ];

        foreach ($villages as $villageData) {
            Village::updateOrCreate(
                ['slug' => $villageData['slug']],
                $villageData
            );
        }

        $this->command->info('Created ' . count($villages) . ' villages as independent system.');
    }
}
