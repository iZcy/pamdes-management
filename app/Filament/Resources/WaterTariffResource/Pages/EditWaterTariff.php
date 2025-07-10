<?php

namespace App\Filament\Resources\WaterTariffResource\Pages;

use App\Filament\Resources\WaterTariffResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaterTariff extends EditRecord
{
    protected static string $resource = WaterTariffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
