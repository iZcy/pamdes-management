<?php

// app/Console/Commands/SyncVillageData.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VillageApiService;

class SyncVillageData extends Command
{
    protected $signature = 'pamdes:sync-village {village_id?}';
    protected $description = 'Sync data with village system';

    public function handle(VillageApiService $villageService)
    {
        $villageId = $this->argument('village_id');

        if ($villageId) {
            $this->syncVillage($villageService, $villageId);
        } else {
            $villages = $villageService->getActiveVillages();
            foreach ($villages as $village) {
                $this->syncVillage($villageService, $village['id']);
            }
        }
    }

    protected function syncVillage(VillageApiService $villageService, string $villageId)
    {
        $this->info("Syncing village {$villageId}...");

        // Generate summary data
        $summaryData = app(\App\Http\Controllers\Api\ReportController::class)
            ->villageReport(request()->merge(['village_id' => $villageId]))
            ->getData();

        $success = $villageService->sendPamdesSummary($villageId, $summaryData->data);

        if ($success) {
            $this->info("Successfully synced village {$villageId}");
        } else {
            $this->error("Failed to sync village {$villageId}");
        }
    }
}
