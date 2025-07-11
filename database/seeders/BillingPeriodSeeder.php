<?php
// database/seeders/BillingPeriodSeeder.php - Fixed to use existing villages

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BillingPeriod;
use App\Models\Village;

class BillingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing villages from the database
        $villages = Village::where('is_active', true)->get();

        if ($villages->isEmpty()) {
            $this->command->error('No active villages found. Please run VillageSeeder first.');
            return;
        }

        foreach ($villages as $village) {
            $this->command->info("Creating billing periods for village: {$village->name}");

            // Create periods for last 6 months + current month
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $startDate = $date->copy()->startOfMonth();
                $endDate = $date->copy()->endOfMonth();
                $dueDate = $date->copy()->addDays(15); // Due 15 days after month end

                // Determine status based on period
                $status = 'inactive';
                if ($i === 0) {
                    $status = 'active'; // Current month
                } elseif ($i <= 2) {
                    $status = 'completed'; // Last 2 months
                }

                BillingPeriod::create([
                    'year' => $date->year,
                    'month' => $date->month,
                    'village_id' => $village->id,
                    'status' => $status,
                    'reading_start_date' => $startDate,
                    'reading_end_date' => $endDate,
                    'billing_due_date' => $dueDate,
                ]);
            }

            $this->command->info("Created 7 billing periods for {$village->name}");
        }

        $totalPeriods = BillingPeriod::count();
        $this->command->info("Total billing periods created: {$totalPeriods}");
    }
}
