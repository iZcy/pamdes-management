<?php
// app/Rules/TariffRangeValidation.php - Custom validation for tariff ranges

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\WaterTariff;
use App\Services\TariffRangeService;

class TariffRangeValidation implements ValidationRule
{
    protected string $villageId;
    protected ?WaterTariff $currentRecord;
    protected string $field; // 'min' or 'max'

    public function __construct(string $villageId, string $field = 'min', ?WaterTariff $currentRecord = null)
    {
        $this->villageId = $villageId;
        $this->currentRecord = $currentRecord;
        $this->field = $field;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value) || $value < 0) {
            $fail('The value must be a positive number.');
            return;
        }

        $value = (int) $value;
        $service = app(TariffRangeService::class);

        try {
            if ($this->currentRecord) {
                // Validating an update
                $this->validateUpdate($service, $value, $fail);
            } else {
                // Validating a new range creation
                $this->validateCreation($service, $value, $fail);
            }
        } catch (\Exception $e) {
            $fail($e->getMessage());
        }
    }

    protected function validateCreation(TariffRangeService $service, int $value, Closure $fail): void
    {
        $existingTariffs = WaterTariff::where('village_id', $this->villageId)
            ->orderBy('usage_min')
            ->get();

        // Check for exact duplicate minimum
        if ($existingTariffs->where('usage_min', $value)->count() > 0) {
            $fail("A tariff range already starts at {$value} m³.");
            return;
        }

        // Allow creation - the system will handle auto-adjustments and range splitting
        // Only check for truly invalid scenarios

        // Check if value is negative
        if ($value < 0) {
            $fail("Range minimum cannot be negative.");
            return;
        }

        // The system will automatically:
        // 1. Split existing ranges if the new minimum falls within them
        // 2. Adjust adjacent ranges to maintain proper gaps
        // 3. Handle all edge cases with proper gap enforcement
        
        // No additional validation needed - let the service handle the smart insertion
    }

    protected function validateUpdate(TariffRangeService $service, int $value, Closure $fail): void
    {
        $fields = $service->getEditableFields($this->currentRecord);

        if ($this->field === 'min' && !$fields['can_edit_min']) {
            $fail('Only the last (infinite) range can edit minimum value.');
            return;
        }

        if ($this->field === 'max' && !$fields['can_edit_max']) {
            $fail('The last (infinite) range cannot have a maximum value.');
            return;
        }

        $existingTariffs = WaterTariff::where('village_id', $this->villageId)
            ->where('tariff_id', '!=', $this->currentRecord->tariff_id)
            ->orderBy('usage_min')
            ->get();

        if ($this->field === 'min') {
            // Validate minimum update - must maintain gap
            $previousTariff = $existingTariffs->where('usage_min', '<', $this->currentRecord->usage_min)->last();

            if ($previousTariff) {
                $requiredMin = $previousTariff->usage_max + 1;
                if ($value < $requiredMin) {
                    $fail("Minimum must be at least {$requiredMin} m³ to maintain gap from previous range ending at {$previousTariff->usage_max} m³.");
                    return;
                }
            }
            
            // Check for duplicate minimum
            if ($existingTariffs->where('usage_min', $value)->count() > 0) {
                $fail("A tariff range already starts at {$value} m³.");
                return;
            }
        }

        if ($this->field === 'max') {
            // Validate maximum update - must maintain gap
            $nextTariff = $existingTariffs->where('usage_min', '>', $this->currentRecord->usage_min)->first();

            if ($nextTariff) {
                $maxAllowed = $nextTariff->usage_min - 1;
                if ($value > $maxAllowed) {
                    $fail("Maximum cannot exceed {$maxAllowed} m³ to maintain gap from next range starting at {$nextTariff->usage_min} m³.");
                    return;
                }
            }

            if ($value < $this->currentRecord->usage_min) {
                $fail("Maximum cannot be less than minimum {$this->currentRecord->usage_min}.");
                return;
            }
        }
    }

    /**
     * Get allowed range for a field
     */
    public static function getAllowedRange(string $villageId, string $field, ?WaterTariff $currentRecord = null): array
    {
        $existingTariffs = WaterTariff::where('village_id', $villageId)
            ->when($currentRecord, fn($q) => $q->where('tariff_id', '!=', $currentRecord->tariff_id))
            ->orderBy('usage_min')
            ->get();

        if (!$currentRecord) {
            // For new ranges, find gaps
            $allowedStarts = [0];

            foreach ($existingTariffs as $tariff) {
                if ($tariff->usage_max !== null) {
                    $allowedStarts[] = $tariff->usage_max + 1;
                }
            }

            return [
                'allowed_values' => $allowedStarts,
                'min' => 0,
                'max' => null, // No upper limit for new ranges
            ];
        }

        $service = app(TariffRangeService::class);
        $fields = $service->getEditableFields($currentRecord);

        if ($field === 'min' && $fields['can_edit_min']) {
            $previousTariff = $existingTariffs->where('usage_min', '<', $currentRecord->usage_min)->last();

            return [
                'min' => $previousTariff ? $previousTariff->usage_max + 1 : 0,
                'max' => null,
            ];
        }

        if ($field === 'max' && $fields['can_edit_max']) {
            $nextTariff = $existingTariffs->where('usage_min', '>', $currentRecord->usage_min)->first();

            return [
                'min' => $currentRecord->usage_min,
                'max' => $nextTariff ? $nextTariff->usage_min - 1 : null,
            ];
        }

        return ['min' => null, 'max' => null]; // Field not editable
    }
}
