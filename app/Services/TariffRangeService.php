<?php
// app/Services/TariffRangeService.php - Fixed with smart range splitting

namespace App\Services;

use App\Models\WaterTariff;
use Illuminate\Support\Facades\DB;

class TariffRangeService
{
    /**
     * Create a new tariff range for a village
     * Automatically splits existing ranges if needed
     */
    public function createTariffRange(string $villageId, int $newMin, float $price): WaterTariff
    {
        return DB::transaction(function () use ($villageId, $newMin, $price) {
            $existingTariffs = WaterTariff::where('village_id', $villageId)
                ->orderBy('usage_min')
                ->lockForUpdate()
                ->get();

            // Basic validation
            if ($newMin < 0) {
                throw new \Exception('Minimum usage cannot be negative');
            }

            // Check if this exact minimum already exists
            if ($existingTariffs->where('usage_min', $newMin)->count() > 0) {
                throw new \Exception("A tariff range already starts at {$newMin} m³");
            }

            // Find the range that contains this new minimum
            $containingRange = $this->findContainingRange($existingTariffs, $newMin);

            if ($containingRange) {
                // Split the existing range
                $this->splitExistingRange($containingRange, $newMin, $price);
            } else {
                // Insert at the appropriate position
                $this->insertNewRange($existingTariffs, $newMin, $price, $villageId);
            }

            // Return the newly created tariff
            return WaterTariff::where('village_id', $villageId)
                ->where('usage_min', $newMin)
                ->first();
        });
    }

    /**
     * Find the range that contains the new minimum value
     */
    private function findContainingRange($existingTariffs, int $newMin): ?WaterTariff
    {
        foreach ($existingTariffs as $tariff) {
            // Check if newMin falls within this range
            if (
                $newMin > $tariff->usage_min &&
                ($tariff->usage_max === null || $newMin <= $tariff->usage_max)
            ) {
                return $tariff;
            }
        }
        return null;
    }

    /**
     * Split an existing range by inserting a new tariff in the middle
     */
    private function splitExistingRange(WaterTariff $existingRange, int $newMin, float $newPrice): void
    {
        $originalMax = $existingRange->usage_max;

        // Update the existing range to end at (newMin - 1)
        $existingRange->usage_max = $newMin - 1;
        $existingRange->save();

        // Create the new range starting at newMin
        WaterTariff::create([
            'village_id' => $existingRange->village_id,
            'usage_min' => $newMin,
            'usage_max' => $originalMax, // Keep the original max (could be null for infinite)
            'price_per_m3' => $newPrice,
            'is_active' => true,
        ]);
    }

    /**
     * Insert a new range at the appropriate position without splitting
     */
    private function insertNewRange($existingTariffs, int $newMin, float $price, string $villageId): void
    {
        $insertPosition = $this->findInsertPosition($existingTariffs, $newMin);

        // Adjust the previous range if needed
        if ($insertPosition > 0) {
            $previousRange = $existingTariffs->get($insertPosition - 1);
            if ($previousRange && ($previousRange->usage_max === null || $previousRange->usage_max >= $newMin)) {
                $previousRange->usage_max = $newMin - 1;
                $previousRange->save();
            }
        }

        // Determine the max for the new range
        $newMax = null;
        if ($insertPosition < $existingTariffs->count()) {
            $nextRange = $existingTariffs->get($insertPosition);
            $newMax = $nextRange->usage_min - 1;
        }

        // Create the new range
        WaterTariff::create([
            'village_id' => $villageId,
            'usage_min' => $newMin,
            'usage_max' => $newMax,
            'price_per_m3' => $price,
            'is_active' => true,
        ]);
    }

    /**
     * Update an existing tariff range with smart adjustment
     */
    public function updateTariffRange(WaterTariff $tariff, ?int $newMax = null, ?int $newMin = null, ?float $newPrice = null): WaterTariff
    {
        return DB::transaction(function () use ($tariff, $newMax, $newMin, $newPrice) {
            $existingTariffs = WaterTariff::where('village_id', $tariff->village_id)
                ->where('tariff_id', '!=', $tariff->tariff_id)
                ->orderBy('usage_min')
                ->lockForUpdate()
                ->get();

            $isLastRange = $this->isLastRange($tariff, $existingTariffs);

            // Validate the update
            if ($isLastRange && $newMax !== null) {
                throw new \Exception('Cannot set maximum for the last (infinite) range');
            }

            if (!$isLastRange && $newMin !== null) {
                throw new \Exception('Can only edit minimum value for the last (infinite) range');
            }

            if ($isLastRange && $newMin !== null) {
                $this->validateMinUpdate($existingTariffs, $tariff, $newMin);
                $this->adjustRangesForMinUpdate($existingTariffs, $tariff, $newMin);
                $tariff->usage_min = $newMin;
            }

            if (!$isLastRange && $newMax !== null) {
                $this->validateMaxUpdate($existingTariffs, $tariff, $newMax);
                $this->adjustRangesForMaxUpdate($existingTariffs, $tariff, $newMax);
                $tariff->usage_max = $newMax;
            }

            if ($newPrice !== null) {
                $tariff->price_per_m3 = $newPrice;
            }

            $tariff->save();
            return $tariff;
        });
    }

    /**
     * Delete a tariff range and adjust remaining ranges
     */
    public function deleteTariffRange(WaterTariff $tariff): void
    {
        DB::transaction(function () use ($tariff) {
            $existingTariffs = WaterTariff::where('village_id', $tariff->village_id)
                ->where('tariff_id', '!=', $tariff->tariff_id)
                ->orderBy('usage_min')
                ->lockForUpdate()
                ->get();

            $this->adjustRangesForDeletion($existingTariffs, $tariff);
            $tariff->delete();
        });
    }

    /**
     * Get editable fields for a tariff range
     */
    public function getEditableFields(WaterTariff $tariff): array
    {
        $existingTariffs = WaterTariff::where('village_id', $tariff->village_id)
            ->where('tariff_id', '!=', $tariff->tariff_id)
            ->orderBy('usage_min')
            ->get();

        $isLastRange = $this->isLastRange($tariff, $existingTariffs);

        return [
            'can_edit_min' => $isLastRange,
            'can_edit_max' => !$isLastRange,
            'can_edit_price' => true,
            'is_last_range' => $isLastRange,
        ];
    }

    // Private helper methods

    private function findInsertPosition($existingTariffs, int $newMin): int
    {
        foreach ($existingTariffs as $index => $tariff) {
            if ($newMin < $tariff->usage_min) {
                return $index;
            }
        }
        return $existingTariffs->count();
    }

    private function isLastRange(WaterTariff $tariff, $otherTariffs): bool
    {
        return $tariff->usage_max === null ||
            !$otherTariffs->where('usage_min', '>', $tariff->usage_min)->count();
    }

    private function validateMaxUpdate($existingTariffs, WaterTariff $tariff, int $newMax): void
    {
        // Find the next tariff
        $nextTariff = $existingTariffs->where('usage_min', '>', $tariff->usage_min)->first();

        if ($nextTariff && $newMax >= $nextTariff->usage_min) {
            throw new \Exception("New maximum {$newMax} would conflict with next range starting at {$nextTariff->usage_min}");
        }

        if ($newMax < $tariff->usage_min) {
            throw new \Exception("Maximum cannot be less than minimum {$tariff->usage_min}");
        }
    }

    private function validateMinUpdate($existingTariffs, WaterTariff $tariff, int $newMin): void
    {
        // Find the previous tariff
        $previousTariff = $existingTariffs->where('usage_min', '<', $tariff->usage_min)->last();

        if ($previousTariff && $newMin <= $previousTariff->usage_max) {
            throw new \Exception("New minimum {$newMin} would conflict with previous range ending at {$previousTariff->usage_max}");
        }

        if ($newMin < 0) {
            throw new \Exception('Minimum usage cannot be negative');
        }

        // Check if this exact minimum already exists
        if ($existingTariffs->where('usage_min', $newMin)->count() > 0) {
            throw new \Exception("A tariff range already starts at {$newMin} m³");
        }
    }

    private function adjustRangesForMaxUpdate($existingTariffs, WaterTariff $tariff, int $newMax): void
    {
        // Find and update the next tariff's minimum
        $nextTariff = $existingTariffs->where('usage_min', '>', $tariff->usage_min)->first();

        if ($nextTariff) {
            $nextTariff->usage_min = $newMax + 1;
            $nextTariff->save();
        }
    }

    private function adjustRangesForMinUpdate($existingTariffs, WaterTariff $tariff, int $newMin): void
    {
        // Find and update the previous tariff's maximum
        $previousTariff = $existingTariffs->where('usage_min', '<', $tariff->usage_min)->last();

        if ($previousTariff) {
            $previousTariff->usage_max = $newMin - 1;
            $previousTariff->save();
        }
    }

    private function adjustRangesForDeletion($existingTariffs, WaterTariff $deletingTariff): void
    {
        $previousTariff = $existingTariffs->where('usage_min', '<', $deletingTariff->usage_min)->last();
        $nextTariff = $existingTariffs->where('usage_min', '>', $deletingTariff->usage_min)->first();

        if ($previousTariff && $nextTariff) {
            // Connect previous range to next range
            $previousTariff->usage_max = $nextTariff->usage_min - 1;
            $previousTariff->save();
        } elseif ($previousTariff && !$nextTariff) {
            // Previous range becomes infinite
            $previousTariff->usage_max = null;
            $previousTariff->save();
        }
    }

    /**
     * Get all tariffs for a village in proper order with range info
     */
    public function getVillageTariffs(string $villageId): array
    {
        $tariffs = WaterTariff::where('village_id', $villageId)
            ->orderBy('usage_min')
            ->get();

        return $tariffs->map(function ($tariff) {
            $editableFields = $this->getEditableFields($tariff);

            return [
                'id' => $tariff->tariff_id,
                'range_display' => $tariff->usage_range,
                'usage_min' => $tariff->usage_min,
                'usage_max' => $tariff->usage_max,
                'price_per_m3' => $tariff->price_per_m3,
                'is_active' => $tariff->is_active,
                'editable_fields' => $editableFields,
                'can_delete' => true, // Can always delete, system will adjust
            ];
        })->toArray();
    }

    /**
     * Get suggested ranges based on existing tariffs
     */
    public function getSuggestedRanges(string $villageId): array
    {
        $existingTariffs = WaterTariff::where('village_id', $villageId)
            ->orderBy('usage_min')
            ->get();

        if ($existingTariffs->isEmpty()) {
            return [
                ['min' => 0, 'suggestion' => '0+ m³ (Base rate)'],
                ['min' => 11, 'suggestion' => '11+ m³ (Medium usage)'],
                ['min' => 21, 'suggestion' => '21+ m³ (High usage)'],
            ];
        }

        $suggestions = [];

        // Find gaps between existing ranges
        foreach ($existingTariffs as $index => $tariff) {
            $nextTariff = $existingTariffs->get($index + 1);

            if ($nextTariff && $tariff->usage_max !== null) {
                $gap = $nextTariff->usage_min - $tariff->usage_max;
                if ($gap > 1) {
                    $suggestedMin = $tariff->usage_max + 1;
                    $suggestions[] = [
                        'min' => $suggestedMin,
                        'suggestion' => "{$suggestedMin}+ m³ (Between {$tariff->usage_range} and {$nextTariff->usage_range})"
                    ];
                }
            }

            // Suggest splitting large infinite ranges
            if ($tariff->usage_max === null && $tariff->usage_min === 0) {
                $suggestions[] = [
                    'min' => 11,
                    'suggestion' => '11+ m³ (Split base rate for higher usage)'
                ];
                $suggestions[] = [
                    'min' => 21,
                    'suggestion' => '21+ m³ (Premium rate for high usage)'
                ];
            }
        }

        return $suggestions;
    }
}
