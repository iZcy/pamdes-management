<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * DISABLED: Payments should only be made through bill pay actions, not created manually
 * This page is kept for backward compatibility but not accessible through routes
 */
class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    // protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    // {
    //     // Check if a payment already exists for the same bill
    //     $existingPayment = \App\Models\Payment::where('bill_id', $data['bill_id'])->first();

    //     if ($existingPayment) {
    //         Notification::make()
    //             ->title('Error')
    //             ->body('A payment for this bill already exists.')
    //             ->danger()
    //             ->send();

    //         $this->halt();
    //     }

    //     if (empty($data['payment_date'])) {
    //         $data['payment_date'] = now();
    //     }

    //     return parent::handleRecordCreation($data);
    // }
}
