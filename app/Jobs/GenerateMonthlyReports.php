<?php
// app/Jobs/GenerateMonthlyReports.php - New job for independent system

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Village;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateMonthlyReports implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $villageId = null,
        public ?string $month = null,
        public ?string $year = null
    ) {
        $this->month = $month ?? now()->format('m');
        $this->year = $year ?? now()->format('Y');
    }

    public function handle(): void
    {
        Log::info("Starting monthly report generation for {$this->year}-{$this->month}");

        $villages = $this->villageId
            ? [Village::find($this->villageId)]
            : Village::active()->get();

        $villages = array_filter($villages); // Remove null values

        foreach ($villages as $village) {
            $this->generateVillageMonthlyReport($village);
        }

        Log::info("Completed monthly report generation");
    }

    protected function generateVillageMonthlyReport(Village $village): void
    {
        try {
            Log::info("Generating monthly report for village: {$village->name}");

            // Generate report data
            $reportController = app(ReportController::class);
            $request = request()->merge(['village_id' => $village->id]);
            $reportData = $reportController->villageReport($request)->getData();

            // Save report to storage
            $fileName = "reports/monthly/{$village->slug}/{$this->year}-{$this->month}.json";

            Storage::put($fileName, json_encode([
                'village' => [
                    'id' => $village->id,
                    'name' => $village->name,
                    'slug' => $village->slug,
                ],
                'period' => [
                    'month' => $this->month,
                    'year' => $this->year,
                ],
                'generated_at' => now()->toISOString(),
                'data' => $reportData->data,
            ], JSON_PRETTY_PRINT));

            Log::info("Monthly report saved for village: {$village->name}", [
                'file' => $fileName,
                'size' => Storage::size($fileName),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to generate monthly report for village {$village->name}: " . $e->getMessage());
        }
    }
}
