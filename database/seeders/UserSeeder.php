<?php
// database/seeders/UserSeeder.php - Fixed domain handling

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

        // Extract the base domain (everything after the first dot)
        // For pamdes.kecamatanbayan.id -> kecamatanbayan.id
        $baseDomain = preg_replace('/^[^.]+\./', '', $domain);

        $this->command->info('Creating users with domain: ' . $domain);
        $this->command->info('Base domain: ' . $baseDomain);

        // Create super admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@' . $domain],
            [
                'name' => 'PAMDes Super Administrator',
                'password' => Hash::make('password'),
                'contact_info' => '+62 812-3456-7890',
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        // Create another super admin
        $systemAdmin = User::firstOrCreate(
            ['email' => 'system@' . $domain],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'contact_info' => '+62 813-1111-2222',
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        // Get all villages to create village-specific admins
        $villages = Village::all();

        foreach ($villages as $village) {
            // Remove "pamdes-" from the slug (if present)
            $slugCleaned = str_replace('pamdes-', '', $village->slug);

            $this->command->info("Creating users for village: {$village->name} (slug: {$slugCleaned})");

            // Create primary village admin
            // Email format: admin@village.basedomain (e.g., admin@senaru.kecamatanbayan.id)
            $villageAdmin = User::firstOrCreate(
                ['email' => 'admin@' . $slugCleaned . '.' . $baseDomain],
                [
                    'name' => 'Admin PAMDes ' . $village->name,
                    'password' => Hash::make('password'),
                    'contact_info' => '+62 813-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                    'role' => 'village_admin',
                    'is_active' => true,
                ]
            );

            // Assign village to admin (primary) - check if not already assigned
            if (!$villageAdmin->villages()->where('villages.id', $village->id)->exists()) {
                $villageAdmin->assignToVillage($village->id, true);
            }

            // Create secondary village admin (operator)
            $villageOperator = User::firstOrCreate(
                ['email' => 'operator@' . $slugCleaned . '.' . $baseDomain],
                [
                    'name' => 'Operator PAMDes ' . $village->name,
                    'password' => Hash::make('password'),
                    'contact_info' => '+62 814-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                    'role' => 'village_admin',
                    'is_active' => true,
                ]
            );

            // Assign village to operator (not primary) - check if not already assigned
            if (!$villageOperator->villages()->where('villages.id', $village->id)->exists()) {
                $villageOperator->assignToVillage($village->id, false);
            }
        }

        // Create collectors for each village
        foreach ($villages as $village) {
            $slugCleaned = str_replace('pamdes-', '', $village->slug);
            $collectorRoles = ['collector'];

            foreach ($collectorRoles as $index => $role) {
                $email = $role . '@' . $slugCleaned . '.' . $baseDomain;

                // Check if user already exists
                $existingUser = User::where('email', $email)->first();

                if (!$existingUser) {
                    $collector = User::create([
                        'name' => ucfirst($role) . ' ' . $village->name,
                        'email' => $email,
                        'password' => Hash::make('password'),
                        'contact_info' => '+62 816-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                        'role' => $role,
                        'is_active' => true,
                    ]);

                    // Assign village to collector (not primary)
                    $collector->assignToVillage($village->id, false);

                    $this->command->info("Created {$role} for {$village->name}: {$email}");
                } else {
                    $this->command->warn("User already exists: {$email}");

                    // Ensure village assignment exists
                    if (!$existingUser->villages()->where('villages.id', $village->id)->exists()) {
                        $existingUser->assignToVillage($village->id, false);
                    }
                }
            }
        }

        // Create a multi-village admin (example)
        if ($villages->count() > 1) {
            $multiVillageAdmin = User::firstOrCreate(
                ['email' => 'multi@' . $domain],
                [
                    'name' => 'Multi Village Administrator',
                    'password' => Hash::make('password'),
                    'contact_info' => '+62 815-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                    'role' => 'village_admin',
                    'is_active' => true,
                ]
            );

            // Assign first two villages
            $firstVillage = $villages->first();
            $secondVillage = $villages->skip(1)->first();

            if (!$multiVillageAdmin->villages()->where('villages.id', $firstVillage->id)->exists()) {
                $multiVillageAdmin->assignToVillage($firstVillage->id, true); // Primary
            }

            if (!$multiVillageAdmin->villages()->where('villages.id', $secondVillage->id)->exists()) {
                $multiVillageAdmin->assignToVillage($secondVillage->id, false); // Secondary
            }
        }

        $this->command->info('');
        $this->command->info('===================');
        $this->command->info('Main Admin URL: https://' . $mainDomain . '/admin');
        $this->command->info('Super Admin Credentials:');
        $this->command->info('  📧 Email: admin@' . $domain);
        $this->command->info('  🔑 Password: password');
        $this->command->info('');
        $this->command->info('Village Admin URLs:');

        foreach ($villages as $village) {
            $villageUrl = str_replace('{village}', $village->slug, $villagePattern);
            $slugCleaned = str_replace('pamdes-', '', $village->slug);
            $this->command->info("  🏘️  {$village->name}: https://{$villageUrl}/admin");
            $this->command->info("     📧 Email: admin@{$slugCleaned}.{$baseDomain}");
            $this->command->info("     🔑 Password: password");
        }

        $this->command->info('');
        $this->command->info('Multi-Village Admin:');
        $this->command->info('  📧 Email: multi@' . $domain);
        $this->command->info('  🔑 Password: password');
        $this->command->info('===================');
    }
}
