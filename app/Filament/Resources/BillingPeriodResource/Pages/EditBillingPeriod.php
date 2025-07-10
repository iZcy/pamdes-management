<?php

namespace App\Filament\Resources\BillingPeriodResource\Pages;

use App\Filament\Resources\BillingPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBillingPeriod extends EditRecord
{
    protected static string $resource = BillingPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
