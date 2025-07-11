<?php
// database/seeders/CollectorSeeder.php - Generate collectors for each village

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Collector;
use App\Models\Village;

class CollectorSeeder extends Seeder
{
    public function run(): void
    {
        $villages = Village::where('is_active', true)->get();

        if ($villages->isEmpty()) {
            $this->command->error('No active villages found.');
            return;
        }

        $totalCollectors = 0;

        // Base collector names pool
        $collectorNames = [
            'Budi Santoso',
            'Siti Nurhaliza',
            'Ahmad Wijaya',
            'Dewi Sartika',
            'Rudi Hartono',
            'Maya Sari',
            'Indra Gunawan',
            'Rina Wati',
            'Bambang Sutrisno',
            'Ani Suryani',
            'Joko Widodo',
            'Lestari Dewi',
            'Hendra Pratama',
            'Dian Kusuma',
            'Agung Prabowo',
            'Fitri Handayani',
            'Wahyu Setiawan',
            'Nurul Hidayah',
            'Eko Saputra',
            'Ratna Sari'
        ];

        foreach ($villages as $village) {
            $this->command->info("Creating collectors for village: {$village->name}");

            // Each village gets 3-6 collectors with different roles
            $collectorsPerVillage = rand(3, 6);
            $villageCollectors = 0;

            // Role distribution: 60% collectors, 30% kasir, 10% admin
            $roleDistribution = ['collector', 'collector', 'collector', 'kasir', 'kasir', 'admin'];
            shuffle($roleDistribution);

            // Get unique names for this village
            $shuffledNames = collect($collectorNames)->shuffle();

            for ($i = 0; $i < $collectorsPerVillage; $i++) {
                $name = $shuffledNames[$i % count($collectorNames)];

                // Add village suffix to make names unique across villages
                $villageSuffix = ucfirst(strtolower($village->slug));
                $uniqueName = $name . ' (' . $villageSuffix . ')';

                $role = $roleDistribution[$i % count($roleDistribution)];

                // Generate realistic phone number
                $phoneNumber = '08' . rand(10, 99) . '-' . rand(1000, 9999) . '-' . rand(1000, 9999);

                // Some collectors might not have phone numbers
                if (rand(1, 10) > 8) {
                    $phoneNumber = null;
                }

                // Create collector
                $collector = Collector::create([
                    'village_id' => $village->id,
                    'name' => $uniqueName,
                    'normalized_name' => Collector::normalizeName($uniqueName),
                    'phone_number' => $phoneNumber,
                    'role' => $role,
                    'is_active' => rand(1, 10) > 1, // 90% active, 10% inactive
                ]);

                $villageCollectors++;
                $this->command->info("  Created: {$collector->name} ({$collector->role})");
            }

            $this->command->info("Created {$villageCollectors} collectors for {$village->name}");
            $totalCollectors += $villageCollectors;
        }

        $this->command->info("Total collectors created: {$totalCollectors}");

        // Show summary statistics
        $this->command->info('');
        $this->command->info('Collector Role Summary:');
        $this->command->info('- Collectors: ' . Collector::where('role', 'collector')->count());
        $this->command->info('- Kasir: ' . Collector::where('role', 'kasir')->count());
        $this->command->info('- Admin: ' . Collector::where('role', 'admin')->count());

        $this->command->info('');
        $this->command->info('Collector Status Summary:');
        $this->command->info('- Active: ' . Collector::where('is_active', true)->count());
        $this->command->info('- Inactive: ' . Collector::where('is_active', false)->count());

        $this->command->info('');
        $this->command->info('Collectors by Village:');
        foreach ($villages as $village) {
            $collectorCount = Collector::where('village_id', $village->id)->count();
            $activeCount = Collector::where('village_id', $village->id)->where('is_active', true)->count();

            $this->command->info("- {$village->name}: {$collectorCount} total ({$activeCount} active)");
        }
    }
}
