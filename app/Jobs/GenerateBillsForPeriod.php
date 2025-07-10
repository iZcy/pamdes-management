<?php
// app/Jobs/GenerateBillsForPeriod.php - Updated for independent system

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\BillingPeriod;
use App\Services\BillingService;
use Illuminate\Support\Facades\Log;

class GenerateBillsForPeriod implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public BillingPeriod $period
    ) {}

    public function handle(BillingService $billingService): void
    {
        Log::info("Starting bill generation for period: {$this->period->period_name}");

        try {
            $bills = $billingService->generateBillsForPeriod($this->period);

            Log::info("Generated {$bills->count()} bills for period: {$this->period->period_name}");

            // Log notification locally instead of sending to external system
            Log::info("Bills generated notification", [
                'village_id' => $this->period->village_id,
                'period' => $this->period->period_name,
                'bills_count' => $bills->count(),
                'total_amount' => $bills->sum('total_amount'),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to generate bills for period {$this->period->period_name}: " . $e->getMessage());
            throw $e;
        }
    }
}
