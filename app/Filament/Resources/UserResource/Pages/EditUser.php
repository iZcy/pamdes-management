<?php

// app/Filament/Resources/UserResource/Pages/EditUser.php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->record;

        // Add village assignments to form data
        if ($user->isVillageAdmin()) {
            $data['villages'] = $user->villages->pluck('id')->toArray();
            $data['primary_village'] = $user->primaryVillage()?->id;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $user = $this->record;
        $villages = $this->data['villages'] ?? [];
        $primaryVillage = $this->data['primary_village'] ?? null;

        if ($user->isVillageAdmin()) {
            // Remove all current village assignments
            $user->villages()->detach();

            // Assign new villages
            foreach ($villages as $villageId) {
                $user->assignToVillage($villageId, $villageId === $primaryVillage);
            }
        }
    }
}
