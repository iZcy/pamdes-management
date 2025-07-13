<?php

namespace App\Filament\Resources\WaterUsageResource\Pages;

use App\Filament\Resources\WaterUsageResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWaterUsage extends CreateRecord
{
    protected static string $resource = WaterUsageResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Check if a water usage record already exists for the same customer and period
        $existingUsage = \App\Models\WaterUsage::where('customer_id', $data['customer_id'])
            ->where('period_id', $data['period_id'])
            ->first();

        if ($existingUsage) {
            Notification::make()
                ->title('Error')
                ->body('A water usage record for this customer and billing period already exists.')
                ->danger()
                ->send();

            $this->halt();
        }

        return parent::handleRecordCreation($data);
    }
}
