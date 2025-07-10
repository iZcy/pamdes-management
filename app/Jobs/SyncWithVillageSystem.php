<?php

// app/Jobs/SyncWithVillageSystem.php - Complete implementation
namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\VillageApiService;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Log;

class SyncWithVillageSystem implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $villageId
    ) {}

    public function handle(VillageApiService $villageService): void
    {
        Log::info("Starting village sync for: {$this->villageId}");

        try {
            // Generate report data
            $reportController = app(ReportController::class);
            $request = request()->merge(['village_id' => $this->villageId]);
            $reportData = $reportController->villageReport($request)->getData();

            // Send to village system
            $success = $villageService->sendPamdesSummary($this->villageId, $reportData->data);

            if ($success) {
                Log::info("Successfully synced village: {$this->villageId}");
            } else {
                Log::warning("Failed to sync village: {$this->villageId}");
            }
        } catch (\Exception $e) {
            Log::error("Error syncing village {$this->villageId}: " . $e->getMessage());
            throw $e;
        }
    }
}
