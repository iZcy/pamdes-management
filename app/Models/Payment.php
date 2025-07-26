<?php
// app/Models/Payment.php - Updated to handle multiple bills (bundle payments)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Payment extends Model
{
    use HasFactory;

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'payment_date',
        'total_amount',
        'change_given',
        'payment_method',
        'transaction_ref',
        'tripay_data',
        'collector_id',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'total_amount' => 'decimal:2',
        'change_given' => 'decimal:2',
        'tripay_data' => 'array',
    ];

    protected $appends = [
        'net_amount',
        'collector_name',
        'bill_count',
    ];

    // Relationships
    public function bills(): BelongsToMany
    {
        return $this->belongsToMany(Bill::class, 'bill_payment', 'payment_id', 'bill_id')
            ->withPivot('amount_paid')
            ->withTimestamps();
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id', 'id');
    }

    // Accessors
    public function getNetAmountAttribute(): float
    {
        return $this->total_amount - $this->change_given;
    }

    public function getCollectorNameAttribute(): ?string
    {
        return $this->collector?->name;
    }

    public function getBillCountAttribute(): int
    {
        return $this->bills()->count();
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

    public function scopeByTransactionRef(Builder $query, string $transactionRef): Builder
    {
        return $query->where('transaction_ref', $transactionRef);
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

    public function isBundle(): bool
    {
        return $this->bills()->count() > 1;
    }

    public function isTripayPayment(): bool
    {
        return $this->transaction_ref !== null;
    }

    // Pay multiple bills at once
    public static function payBills(array $billIds, array $paymentData): self
    {
        $bills = Bill::whereIn('bill_id', $billIds)->where('status', 'unpaid')->get();
        
        if ($bills->isEmpty()) {
            throw new \Exception('No unpaid bills found');
        }

        $totalAmount = $bills->sum('total_amount');
        
        // Create payment
        $payment = self::create([
            'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
            'total_amount' => $totalAmount,
            'change_given' => $paymentData['change_given'] ?? 0,
            'payment_method' => $paymentData['payment_method'] ?? 'cash',
            'transaction_ref' => $paymentData['transaction_ref'] ?? null,
            'tripay_data' => $paymentData['tripay_data'] ?? null,
            'collector_id' => $paymentData['collector_id'] ?? null,
            'notes' => $paymentData['notes'] ?? null,
        ]);

        // Attach bills and mark as paid
        foreach ($bills as $bill) {
            $payment->bills()->attach($bill->bill_id, [
                'amount_paid' => $bill->total_amount
            ]);
            
            $bill->markAsPaid($payment->payment_date);
            
            // Set transaction_ref on the bill if provided
            if (isset($paymentData['transaction_ref'])) {
                $bill->update(['transaction_ref' => $paymentData['transaction_ref']]);
            }
        }

        return $payment;
    }
}
