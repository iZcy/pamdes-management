<?php
// app/Models/WaterTariff.php - Updated with proper village relationship

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
        'village_id',
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
        return $query->where(function ($q) use ($villageId) {
            $q->where('village_id', $villageId)
                ->orWhereNull('village_id'); // Allow global tariffs
        });
    }

    public function scopeForUsage($query, $usage)
    {
        return $query->where('usage_min', '<=', $usage)
            ->where('usage_max', '>=', $usage);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('village_id');
    }

    // Accessors
    public function getUsageRangeAttribute(): string
    {
        return "{$this->usage_min} - {$this->usage_max} m³";
    }

    public function getVillageNameAttribute(): string
    {
        return $this->village?->name ?? 'Global';
    }

    public function getIsGlobalAttribute(): bool
    {
        return $this->village_id === null;
    }

    // Helper methods
    public static function calculateBill($usage, $villageId = null): array
    {
        $tariffs = static::active()
            ->forVillage($villageId)
            ->orderBy('usage_min')
            ->get();

        $totalCharge = 0;
        $remainingUsage = $usage;
        $breakdown = [];

        foreach ($tariffs as $tariff) {
            if ($remainingUsage <= 0) break;

            $bracketUsage = min(
                $remainingUsage,
                $tariff->usage_max - $tariff->usage_min + 1
            );

            if ($usage >= $tariff->usage_min) {
                $charge = $bracketUsage * $tariff->price_per_m3;
                $totalCharge += $charge;

                $breakdown[] = [
                    'range' => "{$tariff->usage_min}-{$tariff->usage_max} m³",
                    'usage' => $bracketUsage,
                    'rate' => $tariff->price_per_m3,
                    'charge' => $charge,
                ];

                $remainingUsage -= $bracketUsage;
            }
        }

        return [
            'total_charge' => $totalCharge,
            'breakdown' => $breakdown,
        ];
    }
}
