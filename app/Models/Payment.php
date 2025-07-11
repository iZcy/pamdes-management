<?php
// app/Models/Payment.php - Updated with collector relationship

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Payment extends Model
{
    use HasFactory;

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'bill_id',
        'payment_date',
        'amount_paid',
        'change_given',
        'payment_method',
        'payment_reference',
        'collector_id',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount_paid' => 'decimal:2',
        'change_given' => 'decimal:2',
    ];

    protected $appends = [
        'net_amount',
        'collector_name',
    ];

    // Relationships
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id', 'bill_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id', 'id');
    }

    // Accessors
    public function getNetAmountAttribute(): float
    {
        return $this->amount_paid - $this->change_given;
    }

    public function getCollectorNameAttribute(): ?string
    {
        return $this->collector?->name;
    }

    // Scopes
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('payment_date', today());
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year);
    }

    public function scopeByMethod(Builder $query, string $method): Builder
    {
        return $query->where('payment_method', $method);
    }

    public function scopeByCollector(Builder $query, int $collectorId): Builder
    {
        return $query->where('collector_id', $collectorId);
    }

    public function scopeForVillage(Builder $query, string $villageId): Builder
    {
        return $query->whereHas('bill.waterUsage.customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        });
    }

    // Helper methods
    public function getPaymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Tunai',
            'transfer' => 'Transfer Bank',
            'qris' => 'QRIS',
            'other' => 'Lainnya',
            default => 'Unknown'
        };
    }

    public function hasChange(): bool
    {
        return $this->change_given > 0;
    }

    public function isExactPayment(): bool
    {
        return $this->amount_paid == $this->bill->total_amount;
    }

    public function getCustomerInfo(): ?object
    {
        return $this->bill?->waterUsage?->customer;
    }

    public function getVillageInfo(): ?object
    {
        return $this->bill?->waterUsage?->customer?->village;
    }
}
