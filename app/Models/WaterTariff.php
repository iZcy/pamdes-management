<?php
// app/Models/WaterTariff.php - Fixed with correct calculation logic and no global tariffs

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterTariff extends Model
{
    use HasFactory;

    protected $primaryKey = 'tariff_id';

    protected $fillable = [
        'usage_min',
        'usage_max',
        'price_per_m3',
        'village_id', // Always required - no more global tariffs
        'is_active',
    ];

    protected $casts = [
        'price_per_m3' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_id', 'id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'tariff_id', 'tariff_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForVillage($query, $villageId)
    {
        return $query->where('village_id', $villageId);
    }

    public function scopeForUsage($query, $usage)
    {
        return $query->where('usage_min', '<=', $usage)
            ->where('usage_max', '>=', $usage);
    }

    // Accessors
    public function getUsageRangeAttribute(): string
    {
        if ($this->usage_max === null) {
            return "{$this->usage_min}+ m³";
        }
        return "{$this->usage_min}-{$this->usage_max} m³";
    }

    public function getVillageNameAttribute(): string
    {
        return $this->village?->name ?? 'Unknown Village';
    }

    // Fixed calculation method
    public static function calculateBill($usage, $villageId): array
    {
        if (!$villageId) {
            throw new \Exception('Village ID is required for tariff calculation');
        }

        $tariffs = static::active()
            ->forVillage($villageId)
            ->orderBy('usage_min')
            ->get();

        if ($tariffs->isEmpty()) {
            throw new \Exception("No active tariffs found for village ID: {$villageId}");
        }

        $totalCharge = 0;
        $breakdown = [];
        $usedSoFar = 0;

        foreach ($tariffs as $index => $tariff) {
            $tierMin = $tariff->usage_min;
            $tierMax = $tariff->usage_max;

            // Skip if we haven't reached this tier yet
            if ($usedSoFar >= $tierMax && $tierMax !== null) continue;

            // Calculate the actual start position for this tier
            $actualStart = max($tierMin, $usedSoFar + 1);

            // Skip if our total usage doesn't reach this tier
            if ($usage < $actualStart) break;

            // Check if this is the last tier (infinite tier)
            $isLastTier = ($index === $tariffs->count() - 1) || ($tierMax === null);

            // Calculate usage in this tier
            if ($isLastTier) {
                // Last tier covers all remaining usage
                $tierUsage = $usage - $usedSoFar;
            } else {
                $tierUsage = min($usage, $tierMax) - max($usedSoFar, $tierMin - 1);
            }

            if ($tierUsage > 0) {
                $charge = $tierUsage * $tariff->price_per_m3;
                $totalCharge += $charge;

                // Format range display
                if ($isLastTier) {
                    $rangeDisplay = "{$tierMin}+ m³";
                } else {
                    $rangeDisplay = "{$tierMin}-{$tierMax} m³";
                }

                $breakdown[] = [
                    'range' => $rangeDisplay,
                    'usage' => $tierUsage,
                    'rate' => $tariff->price_per_m3,
                    'charge' => $charge,
                ];

                $usedSoFar += $tierUsage;

                // If this is the last tier, we're done
                if ($isLastTier) break;
            }
        }

        return [
            'total_charge' => $totalCharge,
            'breakdown' => $breakdown,
        ];
    }
}
