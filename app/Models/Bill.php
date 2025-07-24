<?php
// app/Models/Bill.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bill extends Model
{
    use HasFactory;

    protected $primaryKey = 'bill_id';

    protected $fillable = [
        'usage_id',
        'tariff_id',
        'water_charge',
        'admin_fee',
        'maintenance_fee',
        'total_amount',
        'status',
        'due_date',
        'payment_date',
    ];

    protected $casts = [
        'water_charge' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'maintenance_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'due_date' => 'date',
        'payment_date' => 'date',
    ];

    // Relationships
    public function waterUsage(): BelongsTo
    {
        return $this->belongsTo(WaterUsage::class, 'usage_id', 'usage_id');
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(WaterTariff::class, 'tariff_id', 'tariff_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'bill_id', 'bill_id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'bill_id', 'bill_id')
            ->latest('payment_date');
    }

    public function bundlePayments(): BelongsToMany
    {
        return $this->belongsToMany(BundlePayment::class, 'bundle_payment_bills', 'bill_id', 'bundle_payment_id')
            ->withPivot('bill_amount')
            ->withTimestamps();
    }

    // Accessors
    public function getCustomerAttribute()
    {
        return $this->waterUsage->customer;
    }

    public function getBillingPeriodAttribute()
    {
        return $this->waterUsage->billingPeriod;
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'success',
            'unpaid' => 'warning',
            'overdue' => 'danger',
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'unpaid' &&
            $this->due_date !== null &&
            $this->due_date->isPast();
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->is_overdue || $this->due_date === null) return 0;
        return $this->due_date->diffInDays(now());
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    // Scopes
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'unpaid')
            ->where('due_date', '<', now());
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->whereHas('waterUsage', function ($q) use ($customerId) {
            $q->where('customer_id', $customerId);
        });
    }

    public function scopeForPeriod($query, $periodId)
    {
        return $query->whereHas('waterUsage', function ($q) use ($periodId) {
            $q->where('period_id', $periodId);
        });
    }

    // Helper methods
    public function markAsPaid(array $paymentData): Payment
    {
        $payment = $this->payments()->create(array_merge($paymentData, [
            'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
            'amount_paid' => $paymentData['amount_paid'] ?? $this->total_amount,
        ]));

        $this->update([
            'status' => 'paid',
            'payment_date' => $payment->payment_date,
        ]);

        return $payment;
    }

    public function updateOverdueStatus(): void
    {
        if ($this->status === 'unpaid' && $this->is_overdue) {
            $this->update(['status' => 'overdue']);
        }
    }

    public function canBePaid(): bool
    {
        return in_array($this->status, ['unpaid', 'overdue']);
    }
}
