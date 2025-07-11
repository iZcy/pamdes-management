<?php
// app/Filament/Resources/VariableResource/Pages/EditVariable.php

namespace App\Filament\Resources\VariableResource\Pages;

use App\Filament\Resources\VariableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVariable extends EditRecord
{
    protected static string $resource = VariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Hapus Pengaturan')
                ->modalDescription('Apakah Anda yakin ingin menghapus pengaturan ini? Fitur pembayaran digital akan tidak berfungsi.')
                ->modalSubmitActionLabel('Ya, Hapus'),
        ];
    }
}
