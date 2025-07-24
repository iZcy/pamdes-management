<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundlePaymentBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'bundle_payment_id',
        'bill_id',
        'bill_amount',
    ];

    protected $casts = [
        'bill_amount' => 'decimal:2',
    ];

    // Relationships
    public function bundlePayment(): BelongsTo
    {
        return $this->belongsTo(BundlePayment::class, 'bundle_payment_id', 'bundle_payment_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id', 'bill_id');
    }
}