<?php

namespace App\Filament\Resources\BillResource\Pages;

use App\Filament\Resources\BillResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBill extends CreateRecord
{
    protected static string $resource = BillResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Check if a bill already exists for the same usage
        $existingBill = \App\Models\Bill::where('usage_id', $data['usage_id'])->first();

        if ($existingBill) {
            Notification::make()
                ->title('Error')
                ->body('A bill for this water usage record already exists.')
                ->danger()
                ->send();

            $this->halt();
        }

        return parent::handleRecordCreation($data);
    }
}
