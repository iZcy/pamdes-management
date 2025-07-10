<?php
// app/Models/BillingPeriod.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPeriod extends Model
{
    use HasFactory;

    protected $primaryKey = 'period_id';

    protected $fillable = [
        'year',
        'month',
        'village_id',
        'status',
        'reading_start_date',
        'reading_end_date',
        'billing_due_date',
    ];

    protected $casts = [
        'reading_start_date' => 'date',
        'reading_end_date' => 'date',
        'billing_due_date' => 'date',
    ];

    // Relationships
    public function waterUsages(): HasMany
    {
        return $this->hasMany(WaterUsage::class, 'period_id', 'period_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'period_id', 'period_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForVillage($query, $villageId)
    {
        return $query->where('village_id', $villageId);
    }

    public function scopeCurrent($query)
    {
        return $query->where('year', now()->year)
            ->where('month', now()->month);
    }

    // Accessors
    public function getPeriodNameAttribute(): string
    {
        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        return $monthNames[$this->month] . ' ' . $this->year;
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'completed' => 'primary',
            'inactive' => 'gray',
        };
    }

    // Helper methods
    public function getTotalCustomers(): int
    {
        return $this->waterUsages()->distinct('customer_id')->count();
    }

    public function getTotalBilled(): float
    {
        return $this->bills()->sum('total_amount');
    }

    public function getTotalPaid(): float
    {
        return $this->bills()->where('status', 'paid')->sum('total_amount');
    }

    public function getCollectionRate(): float
    {
        $total = $this->getTotalBilled();
        if ($total == 0) return 0;

        return ($this->getTotalPaid() / $total) * 100;
    }
}
