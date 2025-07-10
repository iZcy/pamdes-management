<?php

// app/Console/Commands/GenerateBills.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BillingService;
use App\Models\BillingPeriod;

class GenerateBills extends Command
{
    protected $signature = 'pamdes:generate-bills {period_id?}';
    protected $description = 'Generate bills for a billing period';

    public function handle(BillingService $billingService)
    {
        $periodId = $this->argument('period_id');

        if ($periodId) {
            $period = BillingPeriod::findOrFail($periodId);
            $periods = collect([$period]);
        } else {
            $periods = BillingPeriod::where('status', 'active')->get();
        }

        foreach ($periods as $period) {
            $this->info("Generating bills for {$period->period_name}...");

            $bills = $billingService->generateBillsForPeriod($period);

            $this->info("Generated {$bills->count()} bills for {$period->period_name}");
        }

        $this->info('Bill generation completed!');
    }
}
