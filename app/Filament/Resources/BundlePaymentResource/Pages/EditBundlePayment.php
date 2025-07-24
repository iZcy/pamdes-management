<?php

namespace App\Filament\Resources\BundlePaymentResource\Pages;

use App\Filament\Resources\BundlePaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBundlePayment extends EditRecord
{
    protected static string $resource = BundlePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}