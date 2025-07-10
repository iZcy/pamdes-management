<?php

namespace App\Filament\Resources\BillingPeriodResource\Pages;

use App\Filament\Resources\BillingPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBillingPeriods extends ListRecords
{
    protected static string $resource = BillingPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
