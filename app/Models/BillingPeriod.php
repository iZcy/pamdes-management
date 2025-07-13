<?php
// app/Models/BillingPeriod.php - Fixed with proper calculations

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Log;

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

    // Add appends to automatically include calculated fields
    protected $appends = [
        'total_customers',
        'total_billed',
        'collection_rate'
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

    // Fixed calculation methods with proper error handling
    public function getTotalCustomersAttribute(): int
    {
        try {
            // Count unique customers who have water usage in this period
            return $this->waterUsages()
                ->distinct('customer_id')
                ->count('customer_id');
        } catch (\Exception $e) {
            Log::error("Error calculating total customers for period {$this->period_id}: " . $e->getMessage());
            return 0;
        }
    }

    public function getTotalBilledAttribute(): float
    {
        try {
            // Sum total amount from bills related to this period
            return $this->bills()->sum('total_amount') ?? 0;
        } catch (\Exception $e) {
            Log::error("Error calculating total billed for period {$this->period_id}: " . $e->getMessage());
            return 0;
        }
    }

    public function getTotalPaidAttribute(): float
    {
        try {
            // Sum total amount from paid bills related to this period
            return $this->bills()->where('status', 'paid')->sum('total_amount') ?? 0;
        } catch (\Exception $e) {
            Log::error("Error calculating total paid for period {$this->period_id}: " . $e->getMessage());
            return 0;
        }
    }

    public function getCollectionRateAttribute(): float
    {
        try {
            $totalBilled = $this->getTotalBilledAttribute();
            if ($totalBilled == 0) return 0;

            $totalPaid = $this->getTotalPaidAttribute();
            return round(($totalPaid / $totalBilled) * 100, 1);
        } catch (\Exception $e) {
            Log::error("Error calculating collection rate for period {$this->period_id}: " . $e->getMessage());
            return 0;
        }
    }

    // Legacy methods for backwards compatibility
    public function getTotalCustomers(): int
    {
        return $this->getTotalCustomersAttribute();
    }

    public function getTotalBilled(): float
    {
        return $this->getTotalBilledAttribute();
    }

    public function getTotalPaid(): float
    {
        return $this->getTotalPaidAttribute();
    }

    public function getCollectionRate(): float
    {
        return $this->getCollectionRateAttribute();
    }

    // Additional helper methods
    public function hasWaterUsages(): bool
    {
        return $this->waterUsages()->exists();
    }

    public function hasBills(): bool
    {
        return $this->bills()->exists();
    }

    public function getBillsCount(): int
    {
        try {
            return $this->bills()->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getPaidBillsCount(): int
    {
        try {
            return $this->bills()->where('status', 'paid')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getUnpaidBillsCount(): int
    {
        try {
            return $this->bills()->whereIn('status', ['unpaid', 'overdue'])->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
