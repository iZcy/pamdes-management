<?php
// app/Models/User.php - Updated with tenant awareness

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'village_id',
        'contact_info',
        'is_active',
        'role', // Add role field
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
        ];
    }

    // Filament User Interface - Tenant-aware
    public function canAccessPanel(Panel $panel): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $tenantContext = config('pamdes.tenant');

        // Super admin can access from any domain
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Regular admin can only access their village's admin panel
        if ($tenantContext && $tenantContext['type'] === 'village_website') {
            return $this->village_id === $tenantContext['village_id'];
        }

        // Block regular users from super admin domain
        if ($tenantContext && $tenantContext['is_super_admin']) {
            return false;
        }

        return false;
    }

    // Relationships
    public function village()
    {
        return $this->belongsTo(Village::class, 'village_id', 'id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForVillage($query, $villageId)
    {
        return $query->where('village_id', $villageId);
    }

    public function scopeSuperAdmins($query)
    {
        return $query->whereNull('village_id')->orWhere('role', 'super_admin');
    }

    public function scopeVillageAdmins($query)
    {
        return $query->whereNotNull('village_id')->where('role', '!=', 'super_admin');
    }

    // Helper methods
    public function isSuperAdmin(): bool
    {
        return $this->village_id === null || $this->role === 'super_admin';
    }

    public function isVillageAdmin(): bool
    {
        return $this->village_id !== null && $this->role !== 'super_admin';
    }

    public function canManageVillage($villageId): bool
    {
        // Super admin can manage any village
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Regular admin can only manage their assigned village
        return $this->village_id === $villageId;
    }

    public function getVillageNameAttribute(): string
    {
        if ($this->village_id && $this->village) {
            return $this->village->name;
        }

        if ($this->isSuperAdmin()) {
            return 'All Villages (Super Admin)';
        }

        return 'No Village Assigned';
    }

    public function getRoleDisplayAttribute(): string
    {
        if ($this->isSuperAdmin()) {
            return 'Super Administrator';
        }

        if ($this->isVillageAdmin()) {
            return 'Village Administrator - ' . $this->village_name;
        }

        return 'User';
    }

    // Get accessible villages for this user
    public function getAccessibleVillages()
    {
        if ($this->isSuperAdmin()) {
            return Village::active()->get();
        }

        if ($this->village_id) {
            return Village::where('id', $this->village_id)->get();
        }

        return collect();
    }

    // Check if user can access specific tenant context
    public function canAccessTenant(array $tenantContext): bool
    {
        // Super admin can access everything
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Village admin can only access their village
        if ($tenantContext['type'] === 'village_website') {
            return $this->village_id === $tenantContext['village_id'];
        }

        // Regular users cannot access super admin context
        if ($tenantContext['is_super_admin']) {
            return false;
        }

        return false;
    }
}
