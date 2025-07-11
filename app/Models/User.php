<?php
// app/Models/User.php - Enhanced with access validation methods

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

    // Filament User Interface - Enhanced with comprehensive access checking
    public function canAccessPanel(Panel $panel): bool
    {
        // Cache key for access check to avoid repeated database queries
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
     * Perform the actual access check logic
     */
    protected function performAccessCheck(): bool
    {
        Log::info("Comprehensive panel access check for user", [
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

        // Super admin check - should work on any domain
        if ($this->isSuperAdmin()) {
            Log::info("Access granted: Super admin", ['user_id' => $this->id]);
            return true;
        }

        // For village admins, check domain context
        if ($this->isVillageAdmin()) {
            return $this->checkVillageAdminAccess();
        }

        Log::info("Access denied: Unknown role or condition", [
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

        // Village admins, collectors, cashiers, operators cannot access super admin domain
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

    /**
     * Check if user's access requirements are still valid
     */
    public function validateCurrentAccess(): bool
    {
        // Clear any cached access data
        $this->clearAccessCache();

        // Perform fresh access check
        return $this->performAccessCheck();
    }

    /**
     * Clear access-related cache for this user
     */
    public function clearAccessCache(): void
    {
        $pattern = "user_panel_access_{$this->id}_*";

        // Note: This is a simplified cache clear - in production you might want
        // to use a more sophisticated cache tagging system
        Cache::forget("user_panel_access_{$this->id}");

        // If using Redis, you could use pattern matching
        // Cache::tags(['user_access', "user_{$this->id}"])->flush();
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
        return in_array($this->role, ['village_admin', 'collector', 'cashier', 'operator']);
    }

    public function isCollector(): bool
    {
        return $this->role === 'collector';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function isOperator(): bool
    {
        return in_array($this->role, ['collector', 'cashier', 'operator']);
    }

    public function getDisplayRoleAttribute(): string
    {
        return match ($this->role) {
            'super_admin' => 'Super Administrator',
            'village_admin' => 'Admin Desa',
            'collector' => 'Penagih',
            'cashier' => 'Kasir',
            'operator' => 'Operator',
            default => 'Unknown'
        };
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

        // Clear cache after village assignment changes
        $this->clearAccessCache();
    }

    public function removeFromVillage(string $villageId): void
    {
        $this->villages()->detach($villageId);
        $this->clearAccessCache();
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

    /**
     * Check if user should be automatically logged out
     */
    public function shouldBeLoggedOut(): bool
    {
        return !$this->validateCurrentAccess();
    }
}
