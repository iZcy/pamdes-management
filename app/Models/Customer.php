<?php
// app/Models/Customer.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Customer extends Model
{
    use HasFactory;

    protected $primaryKey = 'customer_id';

    protected $fillable = [
        'customer_code',
        'name',
        'phone_number',
        'status',
        'address',
        'rt',
        'rw',
        'village',
        'village_id',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    // Relationships
    public function waterUsages(): HasMany
    {
        return $this->hasMany(WaterUsage::class, 'customer_id', 'customer_id');
    }

    public function bills(): HasManyThrough
    {
        return $this->hasManyThrough(
            Bill::class,
            WaterUsage::class,
            'customer_id',
            'usage_id',
            'customer_id',
            'usage_id'
        );
    }

    public function payments(): HasMany
    {
        return $this->hasManyThrough(
            Payment::class,
            Bill::class,
            'usage_id',
            'bill_id',
            'customer_id',
            'bill_id'
        )->through('waterUsages');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByVillage($query, $villageId)
    {
        return $query->where('village_id', $villageId);
    }

    // Accessors & Mutators
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->rt ? "RT {$this->rt}" : null,
            $this->rw ? "RW {$this->rw}" : null,
            $this->village,
        ]);

        return implode(', ', $parts);
    }

    // Helper methods
    public function getCurrentBalance(): float
    {
        return $this->bills()
            ->where('status', 'unpaid')
            ->sum('total_amount');
    }

    public function getLastReading(): ?WaterUsage
    {
        return $this->waterUsages()
            ->latest('usage_date')
            ->first();
    }

    public static function generateCustomerCode($villageId = null): string
    {
        $prefix = $villageId ? strtoupper(substr($villageId, 0, 3)) : 'PAM';
        $latest = static::where('customer_code', 'like', "{$prefix}%")
            ->latest('customer_id')
            ->first();

        if ($latest) {
            $number = (int) substr($latest->customer_code, strlen($prefix)) + 1;
        } else {
            $number = 1;
        }

        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
