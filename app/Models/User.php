<?php
// app/Models/User.php - Updated version with slug-based village context

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

    /* Simplified access check - super admins only on main domain */
    protected function performAccessCheck(): bool
    {
        Log::info("Access check for user", [
            'user_id' => $this->id,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'current_village_id' => config('pamdes.current_village_id'),
            'is_super_admin_domain' => config('pamdes.is_super_admin_domain'),
            'host' => request()->getHost(),
            'tenant' => config('pamdes.tenant'),
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
            $tenant = config('pamdes.tenant');

            Log::info("Super admin access check", [
                'user_id' => $this->id,
                'is_super_admin_domain' => $isSuperAdminDomain,
                'current_village_id' => $currentVillageId,
                'tenant_type' => $tenant['type'] ?? 'unknown',
                'host' => request()->getHost(),
            ]);

            // Super admin can access if:
            // 1. On super admin domain, OR
            // 2. Tenant type is super_admin, OR
            // 3. No village context
            if (
                $isSuperAdminDomain ||
                ($tenant && $tenant['type'] === 'super_admin') ||
                !$currentVillageId
            ) {
                return true;
            } else {
                Log::info("Access denied: Super admin on village domain", [
                    'user_id' => $this->id,
                    'village_id' => $currentVillageId,
                    'host' => request()->getHost(),
                ]);
                return false;
            }
        }

        // For village admins, check domain context
        if ($this->isVillageAdmin()) {
            return $this->checkVillageAdminAccess();
        }

        Log::info("Access denied: Unknown role or conditions not met", [
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

    public function waterUsageReadings()
    {
        return $this->hasMany(WaterUsage::class, 'reader_id');
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
        return $this->role === 'operator';
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
     * Get current village context from slug-based domain
     * Uses the village information set by SetVillageContext middleware
     */
    public function getCurrentVillageContext(): ?string
    {
        // Super admins always see all villages (no village context)
        if ($this->isSuperAdmin()) {
            return null;
        }

        // Get village context from middleware (slug-based)
        $currentVillageId = config('pamdes.current_village_id');
        $tenant = config('pamdes.tenant');

        // If we have a village context from the slug-based domain
        if ($currentVillageId && $tenant && $tenant['type'] === 'village_website') {
            // Verify user has access to this village
            if ($this->hasAccessToVillage($currentVillageId)) {
                return $currentVillageId;
            }

            Log::warning("User does not have access to village from slug", [
                'user_id' => $this->id,
                'village_id' => $currentVillageId,
                'user_villages' => $this->villages->pluck('id')->toArray()
            ]);
        }

        // Final fallback to primary village
        return $this->getPrimaryVillageId();
    }

    /**
     * Get current village object from slug-based domain
     */
    public function getCurrentVillage(): ?object
    {
        $villageId = $this->getCurrentVillageContext();

        if (!$villageId) {
            return null;
        }

        // Try to get from config first (cached from middleware)
        $currentVillage = config('pamdes.current_village');
        if ($currentVillage && $currentVillage['id'] === $villageId) {
            return (object) $currentVillage;
        }

        // Fallback to database query
        return Village::find($villageId);
    }

    /**
     * Get village slug from current domain
     */
    public function getCurrentVillageSlug(): ?string
    {
        $tenant = config('pamdes.tenant');
        $currentVillage = config('pamdes.current_village');

        if ($tenant && $tenant['type'] === 'village_website' && $currentVillage) {
            return $currentVillage['slug'] ?? null;
        }

        return null;
    }

    /**
     * Check if user is accessing from their assigned village domain
     */
    public function isOnAssignedVillageDomain(): bool
    {
        $currentVillageId = config('pamdes.current_village_id');
        $isSuperAdminDomain = config('pamdes.is_super_admin_domain', false);

        // Super admins should only be on super admin domain
        if ($this->isSuperAdmin()) {
            return $isSuperAdminDomain;
        }

        // Village admins should be on a village domain they have access to
        if ($this->isVillageAdmin()) {
            return !$isSuperAdminDomain &&
                $currentVillageId &&
                $this->hasAccessToVillage($currentVillageId);
        }

        return false;
    }

    /**
     * Get the appropriate redirect URL based on user role and current context
     */
    public function getAppropriateRedirectUrl(): ?string
    {
        // Super admins go to main domain
        if ($this->isSuperAdmin()) {
            $mainDomain = config('pamdes.domains.main', env('PAMDES_MAIN_DOMAIN'));
            return "https://{$mainDomain}";
        }

        // Village admins go to their primary village domain
        if ($this->isVillageAdmin()) {
            $primaryVillage = $this->primaryVillage();
            if ($primaryVillage && $primaryVillage->slug) {
                $pattern = config('pamdes.domains.village_pattern', env('PAMDES_VILLAGE_DOMAIN_PATTERN'));
                if ($pattern) {
                    $domain = str_replace('{village}', $primaryVillage->slug, $pattern);
                    return "https://{$domain}";
                }
            }
        }

        return null;
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

        // Clear any village-specific cache as well
        $villageId = config('pamdes.current_village_id');
        if ($villageId) {
            Cache::forget("user_panel_access_{$this->id}_{$villageId}");
        }
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
