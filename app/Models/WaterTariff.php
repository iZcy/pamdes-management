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

    // Fixed calculation method with proper progressive tariff logic
    public static function calculateBill($usage, $villageId): array
    {
        if (!$villageId) {
            throw new \Exception('Village ID is required for tariff calculation');
        }

        if ($usage <= 0) {
            return [
                'total_charge' => 0,
                'breakdown' => [],
            ];
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
        $remainingUsage = $usage;

        foreach ($tariffs as $tariff) {
            if ($remainingUsage <= 0) break;

            $tierMin = $tariff->usage_min;
            $tierMax = $tariff->usage_max;

            // Skip if usage doesn't reach this tier
            if ($usage < $tierMin) break;

            // Calculate how much usage falls within this tier
            $tierStart = $tierMin;
            $tierEnd = $tierMax ?? $usage; // If unlimited tier, use total usage

            // Only process usage that falls within [tierStart, tierEnd] range
            $usageInThisTier = min($usage, $tierEnd) - max($tierStart - 1, 0);
            
            // Make sure we don't exceed remaining usage
            $usageInThisTier = min($usageInThisTier, $remainingUsage);

            if ($usageInThisTier > 0) {
                $charge = $usageInThisTier * $tariff->price_per_m3;
                $totalCharge += $charge;

                // Format range display
                $rangeDisplay = $tierMax === null ? "{$tierMin}+ m³" : "{$tierMin}-{$tierMax} m³";

                $breakdown[] = [
                    'range' => $rangeDisplay,
                    'usage' => $usageInThisTier,
                    'rate' => $tariff->price_per_m3,
                    'charge' => $charge,
                ];

                $remainingUsage -= $usageInThisTier;

                // If this is an unlimited tier (no max), we're done
                if ($tierMax === null) break;
            }
        }

        return [
            'total_charge' => $totalCharge,
            'breakdown' => $breakdown,
        ];
    }
}
