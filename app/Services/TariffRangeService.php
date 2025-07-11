<?php
// app/Services/TariffRangeService.php - Smart tariff range management

namespace App\Services;

use App\Models\WaterTariff;
use Illuminate\Support\Facades\DB;

class TariffRangeService
{
    /**
     * Create a new tariff range for a village
     * Only requires the starting value - automatically calculates ranges
     */
    public function createTariffRange(string $villageId, int $newMin, float $price): WaterTariff
    {
        return DB::transaction(function () use ($villageId, $newMin, $price) {
            $existingTariffs = WaterTariff::where('village_id', $villageId)
                ->orderBy('usage_min')
                ->lockForUpdate()
                ->get();

            // Validate the new minimum value
            $this->validateNewRange($existingTariffs, $newMin);

            // Find where to insert the new range
            $insertPosition = $this->findInsertPosition($existingTariffs, $newMin);

            // Adjust existing ranges if needed
            $this->adjustRangesForInsertion($existingTariffs, $newMin, $insertPosition);

            // Create the new tariff
            $newTariff = WaterTariff::create([
                'village_id' => $villageId,
                'usage_min' => $newMin,
                'usage_max' => $this->calculateNewMax($existingTariffs, $newMin, $insertPosition),
                'price_per_m3' => $price,
                'is_active' => true,
            ]);

            return $newTariff;
        });
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
                throw new \Exception('Can only edit maximum value for non-last ranges');
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

    private function validateNewRange($existingTariffs, int $newMin): void
    {
        foreach ($existingTariffs as $tariff) {
            if ($newMin >= $tariff->usage_min && ($tariff->usage_max === null || $newMin <= $tariff->usage_max)) {
                throw new \Exception("Range {$newMin} conflicts with existing range {$tariff->usage_min}-" .
                    ($tariff->usage_max ?? 'inf'));
            }
        }

        if ($newMin < 0) {
            throw new \Exception('Minimum usage cannot be negative');
        }
    }

    private function findInsertPosition($existingTariffs, int $newMin): int
    {
        foreach ($existingTariffs as $index => $tariff) {
            if ($newMin < $tariff->usage_min) {
                return $index;
            }
        }
        return $existingTariffs->count();
    }

    private function calculateNewMax($existingTariffs, int $newMin, int $insertPosition): ?int
    {
        // If this is the last position, it's infinite
        if ($insertPosition >= $existingTariffs->count()) {
            return null;
        }

        // Otherwise, max is one less than the next range's min
        $nextTariff = $existingTariffs->get($insertPosition);
        return $nextTariff->usage_min - 1;
    }

    private function adjustRangesForInsertion($existingTariffs, int $newMin, int $insertPosition): void
    {
        // If inserting in the middle, adjust the previous range's max
        if ($insertPosition > 0) {
            $previousTariff = $existingTariffs->get($insertPosition - 1);
            $previousTariff->usage_max = $newMin - 1;
            $previousTariff->save();
        }

        // If there's a range after this position, it becomes non-infinite
        if ($insertPosition < $existingTariffs->count()) {
            $nextTariff = $existingTariffs->get($insertPosition);
            // The next range stays as is, but if it was infinite, it's no longer infinite
            // since we're inserting before it
        }
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
}
