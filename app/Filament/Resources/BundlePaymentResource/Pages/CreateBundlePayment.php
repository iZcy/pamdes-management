<?php

namespace App\Filament\Resources\BundlePaymentResource\Pages;

use App\Filament\Resources\BundlePaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBundlePayment extends CreateRecord
{
    protected static string $resource = BundlePaymentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}