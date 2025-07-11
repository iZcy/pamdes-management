<?php
// database/seeders/UserSeeder.php - Updated for dynamic domains

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Village;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Get domain configuration
        $superAdminDomain = config('pamdes.domains.super_admin');
        $mainDomain = config('pamdes.domains.main');

        // Create super admin (can access from super admin domain)
        User::create([
            'name' => 'PAMDes Super Administrator',
            'email' => 'admin@' . $mainDomain,
            'password' => Hash::make('password'),
            'contact_info' => '+62 812-3456-7890',
            'village_id' => null, // Super admin - no village restriction
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // Create another super admin
        User::create([
            'name' => 'System Administrator',
            'email' => 'system@' . $superAdminDomain,
            'password' => Hash::make('password'),
            'contact_info' => '+62 813-1111-2222',
            'village_id' => null,
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // Get all villages to create village-specific admins
        $villages = Village::all();

        foreach ($villages as $village) {
            // Get village domain
            $villageDomain = str_replace('{village}', $village->slug, config('pamdes.domains.village_pattern'));

            // Create primary village admin
            User::create([
                'name' => 'Admin PAMDes ' . $village->name,
                'email' => 'admin@' . $villageDomain,
                'password' => Hash::make('password'),
                'contact_info' => '+62 813-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                'village_id' => $village->id,
                'role' => 'village_admin',
                'is_active' => true,
            ]);

            // Create secondary village admin (kasir/operator)
            User::create([
                'name' => 'Operator PAMDes ' . $village->name,
                'email' => 'operator@' . $villageDomain,
                'password' => Hash::make('password'),
                'contact_info' => '+62 814-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                'village_id' => $village->id,
                'role' => 'village_operator',
                'is_active' => true,
            ]);
        }

        $this->command->info('Created users:');
        $this->command->info('- Super Admins: Can access from ' . $superAdminDomain);
        $this->command->info('- Village Admins: Can access from their respective village domains');
        $this->command->info('- All passwords: password');
        $this->command->info('');
        $this->command->info('Access URLs:');
        $this->command->info('- Super Admin: ' . (request()->isSecure() ? 'https' : 'http') . '://' . $superAdminDomain . '/admin');
        $this->command->info('- Main Website: ' . (request()->isSecure() ? 'https' : 'http') . '://' . $mainDomain);

        foreach ($villages as $village) {
            $villageDomain = str_replace('{village}', $village->slug, config('pamdes.domains.village_pattern'));
            $protocol = request()->isSecure() ? 'https' : 'http';
            $this->command->info("- {$village->name}: {$protocol}://{$villageDomain}/admin");
        }
    }
}
