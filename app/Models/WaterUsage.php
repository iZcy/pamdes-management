<?php
// app/Models/WaterUsage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WaterUsage extends Model
{
    use HasFactory;

    protected $primaryKey = 'usage_id';

    protected $fillable = [
        'customer_id',
        'period_id',
        'initial_meter',
        'final_meter',
        'total_usage_m3',
        'usage_date',
        'reader_id', // Changed from reader_name to reader_id
        'notes',
    ];

    protected $casts = [
        'usage_date' => 'date',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class, 'period_id', 'period_id');
    }

    public function bill(): HasOne
    {
        return $this->hasOne(Bill::class, 'usage_id', 'usage_id');
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reader_id', 'id');
    }

    // Mutators
    public function setFinalMeterAttribute($value)
    {
        $this->attributes['final_meter'] = $value;
        $this->attributes['total_usage_m3'] = max(0, $value - $this->initial_meter);
    }

    // Accessors
    public function getUsageDisplayAttribute(): string
    {
        return number_format($this->total_usage_m3) . ' m³';
    }

    public function getReaderNameAttribute(): string
    {
        // Accessor for backward compatibility and display
        return $this->reader?->name ?? 'Unknown Reader';
    }

    // Scopes
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForPeriod($query, $periodId)
    {
        return $query->where('period_id', $periodId);
    }

    public function scopeByReader($query, $readerId)
    {
        return $query->where('reader_id', $readerId);
    }

    public function scopeByVillage($query, $villageId)
    {
        return $query->whereHas('customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        });
    }

    // Helper methods
    public function generateBill(array $fees = []): Bill
    {
        $calculation = WaterTariff::calculateBill(
            $this->total_usage_m3,
            $this->customer->village_id
        );

        $adminFee = $fees['admin_fee'] ?? 0;
        $maintenanceFee = $fees['maintenance_fee'] ?? 0;
        $waterCharge = $calculation['total_charge'];
        $totalAmount = $waterCharge + $adminFee + $maintenanceFee;

        return Bill::create([
            'customer_id' => $this->customer_id,
            'usage_id' => $this->usage_id,
            'tariff_id' => null,
            'water_charge' => $waterCharge,
            'admin_fee' => $adminFee,
            'maintenance_fee' => $maintenanceFee,
            'total_amount' => $totalAmount,
            'status' => 'unpaid',
            'due_date' => $this->billingPeriod->billing_due_date,
        ]);
    }

    /**
     * Get the previous month's final meter reading for a specific customer and village
     */
    public static function getPreviousMonthFinalMeter($customerId, $currentPeriodId, $villageId): ?float
    {
        // Get the current period details
        $currentPeriod = BillingPeriod::find($currentPeriodId);
        if (!$currentPeriod) {
            return null;
        }

        // Find the previous period for the same village
        $previousPeriod = BillingPeriod::where('village_id', $villageId)
            ->where(function ($query) use ($currentPeriod) {
                $query->where(function ($subQuery) use ($currentPeriod) {
                    // Previous year, December
                    $subQuery->where('year', $currentPeriod->year - 1)
                             ->where('month', 12);
                })->orWhere(function ($subQuery) use ($currentPeriod) {
                    // Same year, previous month
                    $subQuery->where('year', $currentPeriod->year)
                             ->where('month', $currentPeriod->month - 1);
                });
            })
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        if (!$previousPeriod) {
            return null;
        }

        // Get the water usage record for this customer in the previous period
        $previousUsage = static::where('customer_id', $customerId)
            ->where('period_id', $previousPeriod->period_id)
            ->first();

        return $previousUsage?->final_meter;
    }
}
