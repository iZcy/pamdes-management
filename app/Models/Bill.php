<?php
// app/Models/Bill.php - Unified Bill model with bundle functionality

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Bill extends Model
{
    use HasFactory;

    protected $primaryKey = 'bill_id';

    protected $fillable = [
        'bill_ref',
        'bundle_reference',
        'customer_id',
        'usage_id',
        'tariff_id',
        'water_charge',
        'admin_fee',
        'maintenance_fee',
        'total_amount',
        'bill_count',
        'status',
        'payment_method',
        'payment_reference',
        'tripay_data',
        'collector_id',
        'paid_at',
        'expires_at',
        'due_date',
        'payment_date',
        'notes',
    ];

    protected $casts = [
        'water_charge' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'maintenance_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'bill_count' => 'integer',
        'tripay_data' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
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

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id', 'id');
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

    // Bundle relationships
    public function bundledBills(): BelongsToMany
    {
        return $this->belongsToMany(Bill::class, 'bill_bundles', 'bundle_bill_id', 'child_bill_id')
            ->withPivot('original_amount')
            ->withTimestamps();
    }

    public function parentBundle(): BelongsToMany
    {
        return $this->belongsToMany(Bill::class, 'bill_bundles', 'child_bill_id', 'bundle_bill_id')
            ->withPivot('original_amount')
            ->withTimestamps();
    }

    // Accessors
    public function getCustomerFromUsageAttribute()
    {
        return $this->waterUsage?->customer ?? $this->customer;
    }

    public function getBillingPeriodAttribute()
    {
        return $this->waterUsage?->billingPeriod;
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'success',
            'unpaid' => 'warning',
            'overdue' => 'danger',
            'pending' => 'info',
            'failed' => 'danger',
            'expired' => 'danger',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'Lunas',
            'unpaid' => 'Belum Bayar',
            'overdue' => 'Terlambat',
            'pending' => 'Menunggu Pembayaran',
            'failed' => 'Gagal',
            'expired' => 'Kedaluwarsa',
            default => ucfirst($this->status),
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

    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, ['unpaid', 'pending']) &&
            $this->due_date !== null &&
            $this->due_date->isPast();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast() && $this->status !== 'paid';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending' && !$this->is_expired;
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->is_overdue || $this->due_date === null) return 0;
        return $this->due_date->diffInDays(now());
    }

    public function getIsBundleAttribute(): bool
    {
        return $this->bill_count > 1 || $this->bundledBills()->exists();
    }

    public function getIsSingleBillAttribute(): bool
    {
        return $this->bill_count === 1 && !$this->bundledBills()->exists();
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

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'unpaid')
            ->where('due_date', '<', now());
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

    public function scopeBundles(Builder $query): Builder
    {
        return $query->where('bill_count', '>', 1);
    }

    public function scopeSingleBills(Builder $query): Builder
    {
        return $query->where('bill_count', 1);
    }

    // Helper methods
    public static function generateBundleReference(): string
    {
        $prefix = 'BDL';
        $timestamp = now()->format('ymdHis');
        $random = strtoupper(Str::random(4));
        return "{$prefix}{$timestamp}{$random}";
    }

    public function markAsPaid(array $paymentData = []): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
        ]);

        // If this is a bundle, mark all bundled bills as paid
        if ($this->is_bundle) {
            foreach ($this->bundledBills as $bundledBill) {
                if ($bundledBill->status !== 'paid') {
                    $bundledBill->update([
                        'status' => 'paid',
                        'payment_date' => $this->payment_date,
                        'paid_at' => $this->paid_at,
                    ]);

                    // Create individual payment records for each bundled bill
                    $bundledBill->payments()->create([
                        'payment_date' => $this->payment_date,
                        'amount_paid' => $bundledBill->total_amount,
                        'change_given' => 0,
                        'payment_method' => $this->payment_method,
                        'payment_reference' => $this->bundle_reference,
                        'collector_id' => $this->collector_id,
                        'notes' => "Pembayaran bundel: {$this->bundle_reference}",
                    ]);
                }
            }
        } else {
            // Create payment record for single bill
            $this->payments()->create(array_merge($paymentData, [
                'payment_date' => $this->payment_date,
                'amount_paid' => $paymentData['amount_paid'] ?? $this->total_amount,
                'collector_id' => $this->collector_id ?? auth()->id(),
            ]));
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
        return in_array($this->status, ['unpaid', 'overdue', 'pending']) && !$this->is_expired;
    }

    public function getBundledBillsByPeriod(): array
    {
        if (!$this->is_bundle) return [];
        
        $billsByPeriod = [];
        foreach ($this->bundledBills as $bill) {
            $periodName = $bill->waterUsage?->billingPeriod?->period_name ?? 'Unknown Period';
            if (!isset($billsByPeriod[$periodName])) {
                $billsByPeriod[$periodName] = [];
            }
            $billsByPeriod[$periodName][] = $bill;
        }

        return $billsByPeriod;
    }

    public function getTotalSavings(): float
    {
        // Could implement discount logic for bundle payments
        return 0;
    }

    public function createBundle(array $billIds, array $bundleData): Bill
    {
        $bills = Bill::whereIn('bill_id', $billIds)
            ->where('status', 'unpaid')
            ->get();

        if ($bills->isEmpty()) {
            throw new \Exception('No unpaid bills found to bundle');
        }

        // Ensure all bills are from the same customer
        $customerIds = $bills->pluck('customer_id')->unique();
        if ($customerIds->count() > 1) {
            throw new \Exception('All bills must be from the same customer');
        }

        $totalAmount = $bills->sum('total_amount');
        $customerId = $bills->first()->customer_id;

        // Create the bundle bill
        $bundleBill = Bill::create([
            'bundle_reference' => self::generateBundleReference(),
            'customer_id' => $customerId,
            'usage_id' => $bills->first()->usage_id, // Use first bill's usage_id for reference
            'total_amount' => $totalAmount,
            'bill_count' => $bills->count(),
            'status' => 'pending',
            'payment_method' => $bundleData['payment_method'] ?? 'cash',
            'collector_id' => $bundleData['collector_id'] ?? auth()->id(),
            'expires_at' => now()->addDays(7),
            'notes' => $bundleData['notes'] ?? null,
        ]);

        // Link the bills to the bundle
        foreach ($bills as $bill) {
            $bundleBill->bundledBills()->attach($bill->bill_id, [
                'original_amount' => $bill->total_amount,
            ]);
        }

        return $bundleBill;
    }
}