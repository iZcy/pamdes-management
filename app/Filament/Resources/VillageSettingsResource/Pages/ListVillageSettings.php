<?php

namespace App\Filament\Resources\VillageSettingsResource\Pages;

use App\Filament\Resources\VillageSettingsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVillageSettings extends ListRecords
{
    protected static string $resource = VillageSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - villages are not created through this resource
        ];
    }
}
