<?php

namespace App\Filament\Resources\BundlePaymentResource\Pages;

use App\Filament\Resources\BundlePaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBundlePayments extends ListRecords
{
    protected static string $resource = BundlePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}