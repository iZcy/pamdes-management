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
        'reader_name',
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

    // Scopes
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForPeriod($query, $periodId)
    {
        return $query->where('period_id', $periodId);
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
            'usage_id' => $this->usage_id,
            'tariff_id' => null, // Could be set to specific tariff if needed
            'water_charge' => $waterCharge,
            'admin_fee' => $adminFee,
            'maintenance_fee' => $maintenanceFee,
            'total_amount' => $totalAmount,
            'status' => 'unpaid',
            'due_date' => $this->billingPeriod->billing_due_date,
        ]);
    }
}
