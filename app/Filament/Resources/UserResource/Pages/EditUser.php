<?php

// app/Filament/Resources/UserResource/Pages/EditUser.php - Updated

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

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

        // Add village assignments to form data for non-super admin roles
        if (!$user->isSuperAdmin()) {
            $data['villages'] = $user->villages->pluck('id')->toArray();
            $data['primary_village'] = $user->primaryVillage()?->id;
        }

        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // Update basic user data
        $record->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'contact_info' => $data['contact_info'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Update password if provided
        if (!empty($data['password'])) {
            $record->update(['password' => $data['password']]);
        }

        // Handle village assignments
        $this->handleVillageAssignments($record, $data);

        return $record;
    }

    protected function handleVillageAssignments($user, array $data): void
    {
        $currentUser = User::find(Auth::user()->id);
        $villages = $data['villages'] ?? [];
        $primaryVillage = $data['primary_village'] ?? null;

        // Only handle village assignments for non-super admin roles
        if ($user->role !== 'super_admin') {
            // For village admin editing users, ensure they can only assign their own villages
            if ($currentUser && $currentUser->role === 'village_admin') {
                $allowedVillages = $currentUser->getAccessibleVillages()->pluck('id')->toArray();
                $villages = array_intersect($villages, $allowedVillages);

                // Ensure primary village is in allowed villages
                if ($primaryVillage && !in_array($primaryVillage, $allowedVillages)) {
                    $primaryVillage = null;
                }
            }

            // Remove all current village assignments
            $user->villages()->detach();

            // Assign new villages
            foreach ($villages as $villageId) {
                $user->assignToVillage($villageId, $villageId === $primaryVillage);
            }
        }
    }
}
