<?php
// database/seeders/UserSeeder.php - Updated for new user-village relationship with ENV domain

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Village;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Get domain from environment
        $domain = env('APP_DOMAIN', 'pamdes.local');
        $mainDomain = env('PAMDES_MAIN_DOMAIN', $domain);
        $villagePattern = env('PAMDES_VILLAGE_DOMAIN_PATTERN', 'pamdes-{village}.' . $domain);

        // Create super admin
        $superAdmin = User::create([
            'name' => 'PAMDes Super Administrator',
            'email' => 'admin@' . $domain,
            'password' => Hash::make('password'),
            'contact_info' => '+62 812-3456-7890',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // Create another super admin
        $systemAdmin = User::create([
            'name' => 'System Administrator',
            'email' => 'system@' . $domain,
            'password' => Hash::make('password'),
            'contact_info' => '+62 813-1111-2222',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // Get all villages to create village-specific admins
        $villages = Village::all();

        foreach ($villages as $village) {
            // Create primary village admin
            $villageAdmin = User::create([
                'name' => 'Admin PAMDes ' . $village->name,
                'email' => 'admin@' . $village->slug . '.' . $domain,
                'password' => Hash::make('password'),
                'contact_info' => '+62 813-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                'role' => 'village_admin',
                'is_active' => true,
            ]);

            // Assign village to admin (primary)
            $villageAdmin->assignToVillage($village->id, true);

            // Create secondary village admin (operator)
            $villageOperator = User::create([
                'name' => 'Operator PAMDes ' . $village->name,
                'email' => 'operator@' . $village->slug . '.' . $domain,
                'password' => Hash::make('password'),
                'contact_info' => '+62 814-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                'role' => 'village_admin',
                'is_active' => true,
            ]);

            // Assign village to operator (not primary)
            $villageOperator->assignToVillage($village->id, false);
        }

        // Create a multi-village admin (example)
        if ($villages->count() > 1) {
            $multiVillageAdmin = User::create([
                'name' => 'Multi Village Administrator',
                'email' => 'multi@' . $domain,
                'password' => Hash::make('password'),
                'contact_info' => '+62 815-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                'role' => 'village_admin',
                'is_active' => true,
            ]);

            // Assign first two villages
            $firstVillage = $villages->first();
            $secondVillage = $villages->skip(1)->first();

            $multiVillageAdmin->assignToVillage($firstVillage->id, true); // Primary
            $multiVillageAdmin->assignToVillage($secondVillage->id, false); // Secondary
        }

        $this->command->info('Created users with village assignments:');
        $this->command->info('- Super Admins: Can access all villages');
        $this->command->info('- Village Admins: Assigned to specific villages');
        $this->command->info('- Multi-village Admin: Can access multiple villages');
        $this->command->info('- All passwords: password');
        $this->command->info('');
        $this->command->info('Login URLs:');
        $this->command->info('- Super Admin: http://' . $mainDomain . '/admin');

        foreach ($villages as $village) {
            $villageUrl = str_replace('{village}', $village->slug, $villagePattern);
            $this->command->info("- {$village->name}: http://{$villageUrl}/admin");
        }

        $this->command->info('');
        $this->command->info('Login Credentials:');
        $this->command->info('Super Admin:');
        $this->command->info('  - Email: admin@' . $domain);
        $this->command->info('  - Email: system@' . $domain);
        $this->command->info('Village Admins:');
        foreach ($villages as $village) {
            $this->command->info("  - {$village->name} Admin: admin@{$village->slug}.{$domain}");
            $this->command->info("  - {$village->name} Operator: operator@{$village->slug}.{$domain}");
        }
        $this->command->info('Multi-Village Admin:');
        $this->command->info('  - Email: multi@' . $domain);
        $this->command->info('  - Password for all: password');
    }
}
