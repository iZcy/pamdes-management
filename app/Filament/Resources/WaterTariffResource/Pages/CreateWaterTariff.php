<?php
// app/Filament/Resources/WaterTariffResource/Pages/CreateWaterTariff.php

namespace App\Filament\Resources\WaterTariffResource\Pages;

use App\Filament\Resources\WaterTariffResource;
use App\Services\TariffRangeService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateWaterTariff extends CreateRecord
{
    protected static string $resource = WaterTariffResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            $service = app(TariffRangeService::class);

            $tariff = $service->createTariffRange(
                $data['village_id'],
                $data['usage_min'],
                $data['price_per_m3']
            );

            Notification::make()
                ->title('Tarif berhasil dibuat')
                ->body('Rentang tarif telah disesuaikan secara otomatis')
                ->success()
                ->send();

            return $tariff;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal membuat tarif')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
