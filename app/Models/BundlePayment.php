<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BundlePayment extends Model
{
    use HasFactory;

    protected $primaryKey = 'bundle_payment_id';

    protected $fillable = [
        'bundle_reference',
        'customer_id',
        'total_amount',
        'bill_count',
        'status',
        'payment_method',
        'payment_reference',
        'tripay_data',
        'collector_id',
        'paid_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'bill_count' => 'integer',
        'tripay_data' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id', 'id');
    }

    public function bills(): BelongsToMany
    {
        return $this->belongsToMany(Bill::class, 'bundle_payment_bills', 'bundle_payment_id', 'bill_id')
            ->withPivot('bill_amount')
            ->withTimestamps();
    }

    public function bundlePaymentBills(): HasMany
    {
        return $this->hasMany(BundlePaymentBill::class, 'bundle_payment_id', 'bundle_payment_id');
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
            'expired' => 'danger',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'Lunas',
            'pending' => 'Menunggu Pembayaran',
            'failed' => 'Gagal',
            'expired' => 'Kedaluwarsa',
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Tunai',
            'transfer' => 'Transfer Bank',
            'qris' => 'QRIS',
            'other' => 'Lainnya',
            default => 'Unknown'
        };
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast() && $this->status !== 'paid';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending' && !$this->is_expired;
    }

    // Scopes
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '<', now());
    }

    public function scopeForCustomer(Builder $query, $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForVillage(Builder $query, string $villageId): Builder
    {
        return $query->whereHas('customer', function ($q) use ($villageId) {
            $q->where('village_id', $villageId);
        });
    }

    // Helper methods
    public static function generateBundleReference(): string
    {
        $prefix = 'BDL';
        $timestamp = now()->format('ymdHis');
        $random = strtoupper(Str::random(4));
        return "{$prefix}{$timestamp}{$random}";
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Mark all associated bills as paid
        foreach ($this->bills as $bill) {
            if ($bill->status !== 'paid') {
                $bill->update([
                    'status' => 'paid',
                    'payment_date' => now()->toDateString(),
                ]);

                // Create individual payment records for each bill
                $bill->payments()->create([
                    'payment_date' => now()->toDateString(),
                    'amount_paid' => $bill->total_amount,
                    'change_given' => 0,
                    'payment_method' => $this->payment_method,
                    'payment_reference' => $this->bundle_reference,
                    'collector_id' => $this->collector_id,
                    'notes' => "Pembayaran bundel: {$this->bundle_reference}",
                ]);
            }
        }
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => 'expired',
        ]);
    }

    public function canBePaid(): bool
    {
        return in_array($this->status, ['pending']) && !$this->is_expired;
    }

    public function getTotalSavings(): float
    {
        // Could implement discount logic for bundle payments
        return 0;
    }

    public function getBillsByPeriod(): array
    {
        $billsByPeriod = [];
        
        foreach ($this->bills as $bill) {
            $periodName = $bill->waterUsage->billingPeriod->period_name;
            if (!isset($billsByPeriod[$periodName])) {
                $billsByPeriod[$periodName] = [];
            }
            $billsByPeriod[$periodName][] = $bill;
        }

        return $billsByPeriod;
    }
}