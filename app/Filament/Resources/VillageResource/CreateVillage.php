<?php
// app/Filament/Resources/VillageResource/Pages/CreateVillage.php

namespace App\Filament\Resources\VillageResource\Pages;

use App\Filament\Resources\VillageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateVillage extends CreateRecord
{
    protected static string $resource = VillageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['id'] = Str::uuid()->toString();
        $data['established_at'] = now();
        return $data;
    }
}
