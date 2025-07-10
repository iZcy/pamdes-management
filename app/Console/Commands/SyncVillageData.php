<?php
// app/Console/Commands/SyncVillageData.php - Updated for independent system

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VillageService;
use Illuminate\Support\Facades\Log;

class SyncVillageData extends Command
{
    protected $signature = 'pamdes:sync-village {village_id?}';
    protected $description = 'Generate local village reports (no external sync)';

    public function handle(VillageService $villageService)
    {
        $villageId = $this->argument('village_id');

        if ($villageId) {
            $this->generateVillageReport($villageService, $villageId);
        } else {
            $villages = $villageService->getAllVillages();
            foreach ($villages as $village) {
                $this->generateVillageReport($villageService, $village->id);
            }
        }
    }

    protected function generateVillageReport(VillageService $villageService, string $villageId)
    {
        $this->info("Generating report for village {$villageId}...");

        $village = $villageService->getVillageById($villageId);
        if (!$village) {
            $this->error("Village {$villageId} not found");
            return;
        }

        // Generate summary data locally
        $summaryData = app(\App\Http\Controllers\Api\ReportController::class)
            ->villageReport(request()->merge(['village_id' => $villageId]))
            ->getData();

        // Log the report locally instead of sending to external API
        Log::info("Village Report Generated", [
            'village_id' => $villageId,
            'village_name' => $village['name'],
            'data' => $summaryData->data,
        ]);

        $this->info("Successfully generated report for village {$villageId}");
    }
}
