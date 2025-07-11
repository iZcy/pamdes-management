<?php
// app/Models/User.php - Fixed ambiguous column issue

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'contact_info',
        'is_active',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => 'string',
        ];
    }

    // Filament User Interface - Village-aware
    public function canAccessPanel(Panel $panel): bool
    {
        // Simple check: active users can access
        if (!$this->is_active) {
            Log::info("Access denied: User not active", ['user_id' => $this->id]);
            return false;
        }

        // Super admin can always access
        if ($this->isSuperAdmin()) {
            Log::info("Access granted: Super admin", ['user_id' => $this->id]);
            return true;
        }

        // Village admin check
        if ($this->isVillageAdmin()) {
            $currentVillageId = config('pamdes.current_village_id');
            $isSuperAdminDomain = config('pamdes.is_super_admin_domain', false);

            Log::info("Village admin access check", [
                'user_id' => $this->id,
                'current_village_id' => $currentVillageId,
                'is_super_admin_domain' => $isSuperAdminDomain,
            ]);

            // Don't allow village admin on super admin domain
            if ($isSuperAdminDomain) {
                Log::info("Access denied: Village admin on super admin domain", ['user_id' => $this->id]);
                return false;
            }

            // If we have a village context, check access
            if ($currentVillageId) {
                $hasAccess = $this->hasAccessToVillage($currentVillageId);
                Log::info("Village access check result", [
                    'user_id' => $this->id,
                    'village_id' => $currentVillageId,
                    'has_access' => $hasAccess,
                ]);
                return $hasAccess;
            }

            // If no village context, allow access (shouldn't happen but be permissive)
            Log::info("Access granted: No village context, allowing village admin", ['user_id' => $this->id]);
            return true;
        }

        Log::info("Access denied: Unknown role or condition", [
            'user_id' => $this->id,
            'role' => $this->role,
        ]);
        return false;
    }

    // Relationships
    public function villages()
    {
        return $this->belongsToMany(Village::class, 'user_villages', 'user_id', 'village_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryVillage()
    {
        return $this->belongsToMany(Village::class, 'user_villages', 'user_id', 'village_id')
            ->wherePivot('is_primary', true)
            ->withTimestamps()
            ->first();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForVillage($query, $villageId)
    {
        return $query->whereHas('villages', function ($q) use ($villageId) {
            $q->where('villages.id', $villageId);
        });
    }

    public function scopeSuperAdmins($query)
    {
        return $query->where('role', 'super_admin');
    }

    public function scopeVillageAdmins($query)
    {
        return $query->where('role', 'village_admin');
    }

    // Helper methods
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isVillageAdmin(): bool
    {
        return $this->role === 'village_admin';
    }

    public function hasAccessToVillage($villageId): bool
    {
        // Super admin can access any village
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check if user is assigned to this village
        return $this->villages()->where('villages.id', $villageId)->exists();
    }

    public function getAccessibleVillages()
    {
        if ($this->isSuperAdmin()) {
            return Village::where('is_active', true)->get();
        }

        return $this->villages()->where('villages.is_active', true)->get();
    }

    public function getPrimaryVillageId(): ?string
    {
        return $this->primaryVillage()?->id;
    }

    public function assignToVillage(string $villageId, bool $isPrimary = false): void
    {
        // If setting as primary, remove primary flag from other villages
        if ($isPrimary) {
            $this->villages()->updateExistingPivot(
                $this->villages->pluck('villages.id'),
                ['is_primary' => false]
            );
        }

        $this->villages()->syncWithoutDetaching([
            $villageId => ['is_primary' => $isPrimary]
        ]);
    }

    public function removeFromVillage(string $villageId): void
    {
        $this->villages()->detach($villageId);
    }

    public function getCurrentVillageContext(): ?string
    {
        $tenantContext = config('pamdes.tenant');

        if ($tenantContext && $tenantContext['type'] === 'village_website') {
            return $tenantContext['village_id'];
        }

        // Fallback to primary village for super admin
        if ($this->isSuperAdmin()) {
            return $this->getPrimaryVillageId();
        }

        return $this->getPrimaryVillageId();
    }
}
