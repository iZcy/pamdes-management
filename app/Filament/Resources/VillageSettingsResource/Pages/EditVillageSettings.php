<?php

namespace App\Filament\Resources\VillageSettingsResource\Pages;

use App\Filament\Resources\VillageSettingsResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditVillageSettings extends EditRecord
{
    protected static string $resource = VillageSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No delete action - villages cannot be deleted through this resource
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Berhasil')
            ->body('Pengaturan desa telah diperbarui.');
    }
}
