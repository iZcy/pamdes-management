<?php
// Debug the getPreviousMonthFinalMeter method
// Run with: php debug_previous_meter_method.php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WaterUsage;
use App\Models\Customer;
use App\Models\BillingPeriod;

echo "=== Debugging getPreviousMonthFinalMeter Method ===\n\n";

try {
    // Get test data
    $customer = Customer::where('customer_code', 'SEN0001')->first();
    $currentPeriod = BillingPeriod::where('village_id', $customer->village_id)
        ->where('year', 2025)
        ->where('month', 7) // July
        ->first();
    
    echo "Customer: {$customer->customer_code} - {$customer->name}\n";
    echo "Village ID: {$customer->village_id}\n";
    echo "Current Period: {$currentPeriod->period_name} (ID: {$currentPeriod->period_id})\n\n";
    
    // Let's manually step through the method logic
    echo "=== Manual Method Logic Debug ===\n";
    
    // Step 1: Get current period details
    $currentPeriodDetails = BillingPeriod::find($currentPeriod->period_id);
    echo "1. Current period details:\n";
    echo "   - Year: {$currentPeriodDetails->year}\n";
    echo "   - Month: {$currentPeriodDetails->month}\n";
    echo "   - Village ID: {$currentPeriodDetails->village_id}\n";
    
    // Step 2: Calculate previous month and year
    $prevMonth = $currentPeriodDetails->month - 1;
    $prevYear = $currentPeriodDetails->year;
    
    if ($prevMonth <= 0) {
        $prevMonth = 12;
        $prevYear = $currentPeriodDetails->year - 1;
    }
    
    echo "\n2. Calculated previous period:\n";
    echo "   - Previous Year: {$prevYear}\n";
    echo "   - Previous Month: {$prevMonth}\n";
    
    // Step 3: Find the previous period for the same village
    echo "\n3. Looking for previous billing period...\n";
    
    $previousPeriod = BillingPeriod::where('village_id', $customer->village_id)
        ->where(function ($query) use ($currentPeriodDetails) {
            // Previous year, December
            $query->where('year', $currentPeriodDetails->year - 1)
                  ->where('month', 12);
        })
        ->orWhere(function ($query) use ($currentPeriodDetails) {
            // Same year, previous month
            $query->where('year', $currentPeriodDetails->year)
                  ->where('month', $currentPeriodDetails->month - 1);
        })
        ->orderBy('year', 'desc')
        ->orderBy('month', 'desc')
        ->first();
    
    if ($previousPeriod) {
        echo "   ✅ Previous period found: {$previousPeriod->period_name} (ID: {$previousPeriod->period_id})\n";
    } else {
        echo "   ❌ No previous period found\n";
    }
    
    // Step 4: Look for water usage in that period
    echo "\n4. Looking for water usage in previous period...\n";
    
    if ($previousPeriod) {
        $previousUsage = WaterUsage::where('customer_id', $customer->customer_id)
            ->where('period_id', $previousPeriod->period_id)
            ->first();
        
        if ($previousUsage) {
            echo "   ✅ Previous usage found:\n";
            echo "      - Usage ID: {$previousUsage->usage_id}\n";
            echo "      - Initial Meter: {$previousUsage->initial_meter}\n";
            echo "      - Final Meter: {$previousUsage->final_meter}\n";
            echo "      - Usage Date: {$previousUsage->usage_date}\n";
        } else {
            echo "   ❌ No previous usage found for this customer in that period\n";
        }
    }
    
    // Now let's test the actual method
    echo "\n=== Testing Actual Method ===\n";
    
    $result = WaterUsage::getPreviousMonthFinalMeter(
        $customer->customer_id,
        $currentPeriod->period_id,
        $customer->village_id
    );
    
    echo "Method result: " . ($result ?? 'null') . "\n";
    
    // Let's also check what periods and usages exist
    echo "\n=== Available Data Debug ===\n";
    
    echo "All billing periods for this village:\n";
    $allPeriods = BillingPeriod::where('village_id', $customer->village_id)
        ->orderBy('year', 'desc')
        ->orderBy('month', 'desc')
        ->get();
    
    foreach ($allPeriods as $period) {
        echo "   - {$period->period_name} (ID: {$period->period_id})\n";
    }
    
    echo "\nAll water usages for this customer:\n";
    $allUsages = WaterUsage::where('customer_id', $customer->customer_id)
        ->with('billingPeriod')
        ->orderBy('usage_date', 'desc')
        ->get();
    
    foreach ($allUsages as $usage) {
        echo "   - {$usage->billingPeriod->period_name}: Initial {$usage->initial_meter} → Final {$usage->final_meter}\n";
    }
    
    // Let's check if there's an issue with the method's query logic
    echo "\n=== Method Query Debug ===\n";
    
    // Re-implement the method logic step by step to find the issue
    $currentPeriod = BillingPeriod::find($currentPeriod->period_id);
    
    // Find the previous period for the same village
    $previousPeriod = BillingPeriod::where('village_id', $customer->village_id)
        ->where(function ($query) use ($currentPeriod) {
            // Previous year, December
            $query->where('year', $currentPeriod->year - 1)
                  ->where('month', 12);
        })
        ->orWhere(function ($query) use ($currentPeriod) {
            // Same year, previous month
            $query->where('village_id', $customer->village_id) // Add village_id here too!
                  ->where('year', $currentPeriod->year)
                  ->where('month', $currentPeriod->month - 1);
        })
        ->orderBy('year', 'desc')
        ->orderBy('month', 'desc')
        ->first();
    
    if ($previousPeriod) {
        echo "Fixed query - Previous period found: {$previousPeriod->period_name}\n";
        
        $previousUsage = WaterUsage::where('customer_id', $customer->customer_id)
            ->where('period_id', $previousPeriod->period_id)
            ->first();
        
        if ($previousUsage) {
            echo "Fixed query - Previous usage found: Final meter = {$previousUsage->final_meter}\n";
        } else {
            echo "Fixed query - No previous usage found\n";
        }
    } else {
        echo "Fixed query - No previous period found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Debug failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";
?>