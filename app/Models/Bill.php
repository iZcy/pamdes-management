<?php
// app/Models/Bill.php - Simplified Bill model for water usage

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Bill extends Model
{
    use HasFactory;

    protected $primaryKey = 'bill_id';

    protected $fillable = [
        'customer_id',
        'usage_id',
        'tariff_id',
        'water_charge',
        'admin_fee',
        'maintenance_fee',
        'total_amount',
        'status',
        'transaction_ref',
        'due_date',
        'payment_date',
        'notes',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    // Many-to-many relationship with payments (one payment can pay multiple bills)
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'bill_payment', 'bill_id', 'payment_id')
            ->withPivot('amount_paid')
            ->withTimestamps();
    }

    // Latest payment relationship for backward compatibility
    public function getLatestPaymentAttribute()
    {
        return $this->payments()->latest('payment_date')->first();
    }

    // Accessors
    public function getBillingPeriodAttribute()
    {
        return $this->waterUsage?->billingPeriod;
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'Lunas',
            'unpaid' => $this->is_overdue ? 'Terlambat' : 'Belum Bayar',
            default => ucfirst($this->status),
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

    public function getIsPendingPaymentAttribute(): bool
    {
        return $this->status === 'unpaid' && $this->transaction_ref !== null;
    }

    // Scopes
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', 'unpaid');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'unpaid')
            ->where('due_date', '<', now());
    }

    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('status', 'unpaid')
            ->whereNotNull('transaction_ref');
    }

    public function scopeForCustomer(Builder $query, $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForPeriod(Builder $query, $periodId): Builder
    {
        return $query->whereHas('waterUsage', function ($q) use ($periodId) {
            $q->where('period_id', $periodId);
        });
    }

    public function scopeForVillage(Builder $query, string $villageId): Builder
    {
        return $query->whereHas('customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        });
    }

    public function scopeByTransactionRef(Builder $query, string $transactionRef): Builder
    {
        return $query->where('transaction_ref', $transactionRef);
    }

    // Helper methods
    public function markAsPaid(string $paymentDate = null): void
    {
        $this->update([
            'status' => 'paid',
            'payment_date' => $paymentDate ?? now()->toDateString(),
        ]);
    }

    public function canBePaid(): bool
    {
        return $this->status === 'unpaid';
    }

    public function hasPendingPayment(): bool
    {
        return $this->is_pending_payment;
    }

    // Get bills that are part of the same bundle (same transaction_ref)
    public function getBundledBills()
    {
        if (!$this->transaction_ref) {
            return collect([$this]);
        }

        return static::where('transaction_ref', $this->transaction_ref)->get();
    }

    // Calculate bundle total for bills with same transaction_ref
    public function getBundleTotal(): float
    {
        return $this->getBundledBills()->sum('total_amount');
    }
}
