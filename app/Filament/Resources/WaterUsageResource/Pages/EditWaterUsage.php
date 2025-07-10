<?php

namespace App\Filament\Resources\WaterUsageResource\Pages;

use App\Filament\Resources\WaterUsageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaterUsage extends EditRecord
{
    protected static string $resource = WaterUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
