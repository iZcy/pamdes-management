<?php

namespace App\Filament\Resources\WaterUsageResource\Pages;

use App\Filament\Resources\WaterUsageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWaterUsages extends ListRecords
{
    protected static string $resource = WaterUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
