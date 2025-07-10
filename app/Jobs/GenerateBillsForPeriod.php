<?php

// app/Jobs/GenerateBillsForPeriod.php - Complete implementation
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

            // Send notification to village system if enabled
            if (config('village.features.notifications_enabled')) {
                app(\App\Services\VillageApiService::class)->sendNotification(
                    $this->period->village_id,
                    'bills_generated',
                    [
                        'period' => $this->period->period_name,
                        'bills_count' => $bills->count(),
                        'total_amount' => $bills->sum('total_amount'),
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error("Failed to generate bills for period {$this->period->period_name}: " . $e->getMessage());
            throw $e;
        }
    }
}
