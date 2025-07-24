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

        // Ensure at least 1 unit gap - existing range must end at (newMin - 1)
        if ($newMin <= $existingRange->usage_min) {
            throw new \Exception("New minimum {$newMin} must be greater than existing minimum {$existingRange->usage_min}");
        }

        // Check if splitting would create ranges that are too small (less than 1 unit)
        $firstPartSize = ($newMin - 1) - $existingRange->usage_min + 1;
        if ($firstPartSize < 1) {
            throw new \Exception("Cannot create range at {$newMin} - would make the first part of split range too small");
        }

        if ($originalMax !== null) {
            $secondPartSize = $originalMax - $newMin + 1;
            if ($secondPartSize < 1) {
                throw new \Exception("Cannot create range at {$newMin} - would make the second part of split range too small");
            }
        }

        // Update the existing range to end at (newMin - 1) - this ensures gap
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

        // Adjust the previous range if needed (maintain gap)
        if ($insertPosition > 0) {
            $previousRange = $existingTariffs->get($insertPosition - 1);
            if ($previousRange && ($previousRange->usage_max === null || $previousRange->usage_max >= $newMin)) {
                $previousRange->usage_max = $newMin - 1;
                $previousRange->save();
            }
        }

        // Determine the max for the new range and adjust next range if needed
        $newMax = null;
        if ($insertPosition < $existingTariffs->count()) {
            $nextRange = $existingTariffs->get($insertPosition);
            
            // Check if we need to adjust the next range
            if ($nextRange->usage_min <= $newMin) {
                // Auto-adjust the next range to start after our new range
                $nextRange->usage_min = $newMin + 1;
                $nextRange->save();
            }
            
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

            // Handle minimum update (only for last range)
            if ($isLastRange && $newMin !== null) {
                $this->validateMinUpdate($existingTariffs, $tariff, $newMin);
                $this->adjustRangesForMinUpdate($existingTariffs, $tariff, $newMin);
                $tariff->usage_min = $newMin;
            }

            // Handle maximum update (for any non-last range) - auto-adjust next range
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
        $isFirstRange = !$existingTariffs->where('usage_min', '<', $tariff->usage_min)->count();

        return [
            'can_edit_min' => $isLastRange, // Only last range can edit minimum
            'can_edit_max' => !$isLastRange, // All non-last ranges can edit maximum (will auto-adjust next)
            'can_edit_price' => true,
            'is_last_range' => $isLastRange,
            'is_first_range' => $isFirstRange,
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
        if ($newMax < $tariff->usage_min) {
            throw new \Exception("Maximum cannot be less than minimum {$tariff->usage_min}");
        }

        // Find all tariffs that would be affected by this change
        $nextTariff = $existingTariffs->where('usage_min', '>', $tariff->usage_min)->first();
        
        if ($nextTariff) {
            $requiredNextMin = $newMax + 1;
            
            // Check 1: Would this make the next range have less than 1 gap?
            if ($nextTariff->usage_max !== null) {
                $nextRangeSize = $nextTariff->usage_max - $requiredNextMin + 1;
                if ($nextRangeSize < 1) {
                    throw new \Exception("Cannot extend to {$newMax} - would make next range ({$nextTariff->usage_min}-{$nextTariff->usage_max}) have less than 1 unit");
                }
            }
            
            // Check 2: Would this affect more than one adjacent range?
            $tariffAfterNext = $existingTariffs->where('usage_min', '>', $nextTariff->usage_min)->first();
            
            if ($tariffAfterNext) {
                // If extending would force next range to collide with the range after it
                if ($requiredNextMin >= $tariffAfterNext->usage_min) {
                    throw new \Exception("Cannot extend to {$newMax} - would affect multiple adjacent ranges. Maximum allowed: " . ($tariffAfterNext->usage_min - 2));
                }
                
                // Also check if next range would have at least 1 gap from the range after it
                if ($nextTariff->usage_max !== null && $nextTariff->usage_max >= $tariffAfterNext->usage_min - 1) {
                    throw new \Exception("Cannot extend to {$newMax} - would eliminate required gap between adjacent ranges");
                }
            }
            
            // Check 3: Ensure the adjusted next range maintains minimum size
            if ($nextTariff->usage_max !== null) {
                $adjustedRangeSize = $nextTariff->usage_max - $requiredNextMin + 1;
                if ($adjustedRangeSize < 1) {
                    throw new \Exception("Cannot extend to {$newMax} - would make next range too small (minimum 1 unit required)");
                }
            }
        }
    }

    private function validateMinUpdate($existingTariffs, WaterTariff $tariff, int $newMin): void
    {
        if ($newMin < 0) {
            throw new \Exception('Minimum usage cannot be negative');
        }

        // Check if this exact minimum already exists
        if ($existingTariffs->where('usage_min', $newMin)->count() > 0) {
            throw new \Exception("A tariff range already starts at {$newMin} m³");
        }

        // Find the previous tariff that would be affected
        $previousTariff = $existingTariffs->where('usage_min', '<', $tariff->usage_min)->last();

        if ($previousTariff) {
            $requiredPreviousMax = $newMin - 1;
            
            // Check 1: Would this make the previous range have less than 1 unit?
            $previousRangeSize = $requiredPreviousMax - $previousTariff->usage_min + 1;
            if ($previousRangeSize < 1) {
                throw new \Exception("Cannot change minimum to {$newMin} - would make previous range ({$previousTariff->usage_min}-{$previousTariff->usage_max}) have less than 1 unit");
            }
            
            // Check 2: Would this affect more than one adjacent range?
            $tariffBeforePrevious = $existingTariffs->where('usage_min', '<', $previousTariff->usage_min)->last();
            
            if ($tariffBeforePrevious) {
                // Check if adjusting previous range would affect the range before it
                if ($requiredPreviousMax < $previousTariff->usage_min) {
                    throw new \Exception("Cannot change minimum to {$newMin} - would affect multiple adjacent ranges");
                }
                
                // Ensure there's still a gap between the range before previous and the adjusted previous range
                if ($tariffBeforePrevious->usage_max !== null && $tariffBeforePrevious->usage_max >= $previousTariff->usage_min - 1) {
                    throw new \Exception("Cannot change minimum to {$newMin} - would eliminate required gap between ranges");
                }
            }
            
            // Check 3: Basic conflict check
            if ($newMin <= $previousTariff->usage_max) {
                $maxAllowedMin = $previousTariff->usage_max + 1;
                throw new \Exception("New minimum must be at least {$maxAllowedMin} to maintain gap from previous range ending at {$previousTariff->usage_max}");
            }
        }
    }

    private function adjustRangesForMaxUpdate($existingTariffs, WaterTariff $tariff, int $newMax): void
    {
        // Find and update the next tariff's minimum - ensure at least 1 unit gap
        $nextTariff = $existingTariffs->where('usage_min', '>', $tariff->usage_min)->first();

        if ($nextTariff) {
            $newNextMin = $newMax + 1;
            
            // Validate that this doesn't conflict with tariff after next
            $tariffAfterNext = $existingTariffs->where('usage_min', '>', $nextTariff->usage_min)->first();
            if ($tariffAfterNext && $newNextMin > $tariffAfterNext->usage_min) {
                throw new \Exception("Updating maximum to {$newMax} would cause conflict with subsequent tariff");
            }
            
            $nextTariff->usage_min = $newNextMin;
            $nextTariff->save();
        }
    }

    private function adjustRangesForMinUpdate($existingTariffs, WaterTariff $tariff, int $newMin): void
    {
        // Find and update the previous tariff's maximum - ensure at least 1 unit gap
        $previousTariff = $existingTariffs->where('usage_min', '<', $tariff->usage_min)->last();

        if ($previousTariff) {
            $newPreviousMax = $newMin - 1;
            
            // Validate that this doesn't make previous range invalid (max < min)
            if ($newPreviousMax < $previousTariff->usage_min) {
                throw new \Exception("Updating minimum to {$newMin} would make previous range invalid");
            }
            
            $previousTariff->usage_max = $newPreviousMax;
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
