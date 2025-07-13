<?php
// app/Models/User.php - Simplified version

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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

    // Filament User Interface - Simplified
    public function canAccessPanel(Panel $panel): bool
    {
        // Cache key for access check
        $cacheKey = "user_panel_access_{$this->id}_" . md5(serialize([
            config('pamdes.current_village_id'),
            config('pamdes.is_super_admin_domain'),
            $this->updated_at?->timestamp
        ]));

        return Cache::remember($cacheKey, 300, function () {
            return $this->performAccessCheck();
        });
    }

    /**
     * Simplified access check - super admins only on main domain
     */
    protected function performAccessCheck(): bool
    {
        Log::info("Access check for user", [
            'user_id' => $this->id,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'current_village_id' => config('pamdes.current_village_id'),
            'is_super_admin_domain' => config('pamdes.is_super_admin_domain'),
        ]);

        // First check if user is active
        if (!$this->is_active) {
            Log::info("Access denied: User not active", ['user_id' => $this->id]);
            return false;
        }

        // Super admin check - ONLY allow on main/super admin domain
        if ($this->isSuperAdmin()) {
            $isSuperAdminDomain = config('pamdes.is_super_admin_domain', false);
            $currentVillageId = config('pamdes.current_village_id');

            // Super admin can only access main domain or when no village context
            if ($isSuperAdminDomain || !$currentVillageId) {
                Log::info("Access granted: Super admin on main domain", ['user_id' => $this->id]);
                return true;
            } else {
                Log::info("Access denied: Super admin on village domain", [
                    'user_id' => $this->id,
                    'village_id' => $currentVillageId
                ]);
                return false;
            }
        }

        // For village admins, check domain context
        if ($this->isVillageAdmin()) {
            return $this->checkVillageAdminAccess();
        }

        Log::info("Access denied: Unknown role", [
            'user_id' => $this->id,
            'role' => $this->role,
        ]);
        return false;
    }

    /**
     * Check village admin specific access requirements
     */
    protected function checkVillageAdminAccess(): bool
    {
        $currentVillageId = config('pamdes.current_village_id');
        $isSuperAdminDomain = config('pamdes.is_super_admin_domain', false);

        // Village admins cannot access super admin domain
        if ($isSuperAdminDomain) {
            return false;
        }

        // Check if user has any accessible villages
        $accessibleVillages = $this->getAccessibleVillages();
        if ($accessibleVillages->isEmpty()) {
            return false;
        }

        // If we have a village context, check if user has access to it
        if ($currentVillageId) {
            return $this->hasAccessToVillage($currentVillageId);
        }

        return true;
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

    public function payments()
    {
        return $this->hasMany(Payment::class, 'collector_id');
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
        return in_array($this->role, ['village_admin', 'collector', 'operator']);
    }

    public function isCollector(): bool
    {
        return $this->role === 'collector';
    }

    public function isOperator(): bool
    {
        return in_array($this->role, ['collector', 'operator']);
    }

    public function getDisplayRoleAttribute(): string
    {
        return match ($this->role) {
            'super_admin' => 'Super Administrator',
            'village_admin' => 'Admin Desa',
            'collector' => 'Penagih',
            'operator' => 'Operator',
            default => 'Unknown'
        };
    }

    public function hasAccessToVillage($villageId): bool
    {
        // Super admin can access any village (but only from main domain)
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

    /**
     * SIMPLIFIED: Get current village context
     * - Super admin: always null (sees all villages from main domain)
     * - Village admin: current village context from subdomain
     */
    public function getCurrentVillageContext(): ?string
    {
        // Super admins always see all villages (no village context)
        if ($this->isSuperAdmin()) {
            return null;
        }

        // For village admins, use the current village context
        $tenantContext = config('pamdes.tenant');
        $currentVillageId = config('pamdes.current_village_id');

        if ($tenantContext && $tenantContext['type'] === 'village_website') {
            return $tenantContext['village_id'];
        }

        // Fallback to current village ID from middleware
        if ($currentVillageId) {
            return $currentVillageId;
        }

        // Final fallback to primary village
        return $this->getPrimaryVillageId();
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

        // Clear cache after village assignment changes
        $this->clearAccessCache();
    }

    public function removeFromVillage(string $villageId): void
    {
        $this->villages()->detach($villageId);
        $this->clearAccessCache();
    }

    /**
     * Clear access-related cache for this user
     */
    public function clearAccessCache(): void
    {
        Cache::forget("user_panel_access_{$this->id}");
    }

    /**
     * Event handler for when user data changes
     */
    protected static function boot()
    {
        parent::boot();

        // Clear cache when user is updated
        static::updated(function ($user) {
            $user->clearAccessCache();
        });

        // Clear cache when user villages change
        static::saved(function ($user) {
            $user->clearAccessCache();
        });
    }

    /**
     * Check if user's access requirements are still valid
     */
    public function validateCurrentAccess(): bool
    {
        $this->clearAccessCache();
        return $this->performAccessCheck();
    }

    /**
     * Check if user should be automatically logged out
     */
    public function shouldBeLoggedOut(): bool
    {
        return !$this->validateCurrentAccess();
    }
}
