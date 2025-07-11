<?php

// app/Filament/Resources/UserResource/Pages/CreateUser.php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $user = $this->record;
        $villages = $this->data['villages'] ?? [];
        $primaryVillage = $this->data['primary_village'] ?? null;

        // Assign villages to user
        if (!empty($villages) && $user->isVillageAdmin()) {
            foreach ($villages as $villageId) {
                $user->assignToVillage($villageId, $villageId === $primaryVillage);
            }
        }
    }
}
