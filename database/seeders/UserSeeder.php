<?php
// database/seeders/UserSeeder.php - Updated for multi-tenant

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Village;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create super admin (can access from localhost/APP_URL)
        User::create([
            'name' => 'PAMDes Super Administrator',
            'email' => 'admin@pamdes.system',
            'password' => Hash::make('password'),
            'contact_info' => '+62 812-3456-7890',
            'village_id' => null, // Super admin - no village restriction
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // Create another super admin
        User::create([
            'name' => 'System Administrator',
            'email' => 'system@pamdes.local',
            'password' => Hash::make('password'),
            'contact_info' => '+62 813-1111-2222',
            'village_id' => null,
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // Get all villages to create village-specific admins
        $villages = Village::all();

        foreach ($villages as $village) {
            // Create primary village admin
            User::create([
                'name' => 'Admin PAMDes ' . $village->name,
                'email' => 'admin@pamdes-' . $village->slug . '.local',
                'password' => Hash::make('password'),
                'contact_info' => '+62 813-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                'village_id' => $village->id,
                'role' => 'village_admin',
                'is_active' => true,
            ]);

            // Create secondary village admin (kasir/operator)
            User::create([
                'name' => 'Operator PAMDes ' . $village->name,
                'email' => 'operator@pamdes-' . $village->slug . '.local',
                'password' => Hash::make('password'),
                'contact_info' => '+62 814-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                'village_id' => $village->id,
                'role' => 'village_operator',
                'is_active' => true,
            ]);
        }

        $this->command->info('Created users:');
        $this->command->info('- Super Admins: Can access from localhost (APP_URL)');
        $this->command->info('- Village Admins: Can access from pamdes-{village}.local');
        $this->command->info('- All passwords: password');
        $this->command->info('');
        $this->command->info('Access URLs:');
        $this->command->info('- Super Admin: http://localhost/admin');

        foreach ($villages as $village) {
            $this->command->info("- {$village->name}: http://pamdes-{$village->slug}.local/admin");
        }
    }
}
