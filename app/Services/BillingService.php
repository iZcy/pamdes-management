<?php

// app/Services/BillingService.php
namespace App\Services;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Village;
use App\Models\WaterUsage;
use App\Models\WaterTariff;
use Illuminate\Support\Collection;

class BillingService
{
    public function generateBillsForPeriod(BillingPeriod $period): Collection
    {
        $bills = collect();

        $waterUsages = WaterUsage::where('period_id', $period->period_id)
            ->whereDoesntHave('bill')
            ->with('customer')
            ->get();

        foreach ($waterUsages as $usage) {
            $bill = $this->generateBillForUsage($usage);
            if ($bill) {
                $bills->push($bill);
            }
        }

        return $bills;
    }


    public function generateBillForUsage(WaterUsage $usage): ?Bill
    {
        $calculation = WaterTariff::calculateBill(
            $usage->total_usage_m3,
            $usage->customer->village_id
        );

        // Get village-specific fees
        $village = Village::find($usage->customer->village_id);
        $adminFee = $village?->getDefaultAdminFee() ?? 5000;
        $maintenanceFee = $village?->getDefaultMaintenanceFee() ?? 2000;

        $waterCharge = $calculation['total_charge'];
        $totalAmount = $waterCharge + $adminFee + $maintenanceFee;

        return Bill::create([
            'usage_id' => $usage->usage_id,
            'water_charge' => $waterCharge,
            'admin_fee' => $adminFee,
            'maintenance_fee' => $maintenanceFee,
            'total_amount' => $totalAmount,
            'status' => 'unpaid',
            'due_date' => $usage->billingPeriod->billing_due_date,
        ]);
    }

    public function updateOverdueBills(): int
    {
        $overdueCount = Bill::where('status', 'unpaid')
            ->where('due_date', '<', now())
            ->update(['status' => 'overdue']);

        return $overdueCount;
    }

    public function calculateCollectionRate(string $villageId = null): float
    {
        $query = Bill::query();

        if ($villageId) {
            $query->whereHas('waterUsage.customer', function ($q) use ($villageId) {
                $q->where('village_id', $villageId);
            });
        }

        $totalBilled = $query->sum('total_amount');
        $totalPaid = $query->where('status', 'paid')->sum('total_amount');

        if ($totalBilled == 0) return 0;

        return ($totalPaid / $totalBilled) * 100;
    }
}
