<?php
// app/Filament/Resources/VariableResource/Pages/CreateVariable.php

namespace App\Filament\Resources\VariableResource\Pages;

use App\Filament\Resources\VariableResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateVariable extends CreateRecord
{
    protected static string $resource = VariableResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure village_id is set to current village context
        $user = User::find(Auth::user()->id);
        $data['village_id'] = $user?->getCurrentVillageContext();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
