<?php

namespace App\Filament\Resources\BillingPeriodResource\Pages;

use App\Filament\Resources\BillingPeriodResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBillingPeriod extends CreateRecord
{
    protected static string $resource = BillingPeriodResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $existingPeriod = \App\Models\BillingPeriod::where('year', $data['year'])
            ->where('month', $data['month'])
            ->where('village_id', $data['village_id'])
            ->first();

        if ($existingPeriod) {
            Notification::make()
                ->title('Kesalahan')
                ->body('Periode tagihan untuk tahun, bulan, dan desa ini sudah ada.')
                ->danger()
                ->send();

            $this->halt();
        }

        return parent::handleRecordCreation($data);
    }
}
