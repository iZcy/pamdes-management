<?php
// app/Models/BillingPeriod.php - Fixed relationships

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    // Fixed: Bills relationship should go through water_usages
    public function bills(): HasManyThrough
    {
        return $this->hasManyThrough(
            Bill::class,           // Target model
            WaterUsage::class,     // Through model
            'period_id',           // Foreign key on water_usages table
            'usage_id',            // Foreign key on bills table
            'period_id',           // Local key on billing_periods table
            'usage_id'             // Local key on water_usages table
        );
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_id', 'id');
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

    // Helper methods - Fixed to use correct relationships
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
