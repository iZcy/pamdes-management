<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = User::find(Auth::user()->id);
        
        // For non-super admin users, ensure village_id is set
        if (!$user?->isSuperAdmin() && empty($data['village_id'])) {
            // Try to get current village context
            $currentVillageId = $user?->getCurrentVillageContext();
            
            if (!$currentVillageId) {
                // Fallback to first accessible village
                $firstVillage = $user?->getAccessibleVillages()->first();
                $currentVillageId = $firstVillage?->id;
            }
            
            if ($currentVillageId) {
                $data['village_id'] = $currentVillageId;
            } else {
                Notification::make()
                    ->title('Kesalahan')
                    ->body('Tidak dapat menentukan desa untuk pelanggan ini. Pastikan Anda memiliki akses ke setidaknya satu desa atau hubungi administrator.')
                    ->danger()
                    ->send();
                    
                $this->halt();
            }
        }
        
        return parent::handleRecordCreation($data);
    }
}
