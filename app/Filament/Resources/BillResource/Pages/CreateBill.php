<?php

namespace App\Filament\Resources\BillResource\Pages;

use App\Filament\Resources\BillResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * DISABLED: Bills should only be generated from water usage, not created manually
 * This page is kept for backward compatibility but not accessible through routes
 */
class CreateBill extends CreateRecord
{
    protected static string $resource = BillResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Check if a bill already exists for the same usage
        $existingBill = \App\Models\Bill::where('usage_id', $data['usage_id'])->first();

        if ($existingBill) {
            Notification::make()
                ->title('Kesalahan')
                ->body('Tagihan untuk data pembacaan meter ini sudah ada.')
                ->danger()
                ->send();

            $this->halt();
        }

        return parent::handleRecordCreation($data);
    }
}
