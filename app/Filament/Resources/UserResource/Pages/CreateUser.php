<?php
// app/Filament/Resources/UserResource/Pages/CreateUser.php - Updated

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Create the user first
        $user = static::getModel()::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'contact_info' => $data['contact_info'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Handle village assignments
        $this->handleVillageAssignments($user, $data);

        return $user;
    }

    protected function handleVillageAssignments($user, array $data): void
    {
        $currentUser = User::find(Auth::user()->id);
        $villages = $data['villages'] ?? [];
        $primaryVillage = $data['primary_village'] ?? null;

        // Only assign villages for non-super admin roles
        if ($user->role !== 'super_admin' && !empty($villages)) {
            // For village admin creating users, ensure they can only assign their own villages
            if ($currentUser && $currentUser->role === 'village_admin') {
                $allowedVillages = $currentUser->getAccessibleVillages()->pluck('id')->toArray();
                $villages = array_intersect($villages, $allowedVillages);

                // Ensure primary village is in allowed villages
                if ($primaryVillage && !in_array($primaryVillage, $allowedVillages)) {
                    $primaryVillage = null;
                }
            }

            // Assign villages
            foreach ($villages as $villageId) {
                $user->assignToVillage($villageId, $villageId === $primaryVillage);
            }
        }
    }
}
