<?php

namespace App\Filament\Resources\CollectorResource\Pages;

use App\Filament\Resources\CollectorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCollector extends CreateRecord
{
    protected static string $resource = CollectorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['name']) && !isset($data['normalized_name'])) {
            $data['normalized_name'] = \App\Models\Collector::normalizeName($data['name']);
        }

        return $data;
    }
}
