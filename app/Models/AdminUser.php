<?php
// app/Models/AdminUser.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class AdminUser extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'username',
        'email',
        'password',
        'name',
        'role',
        'contact_info',
        'village_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    // Filament User Interface
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && in_array($this->role, ['admin', 'cashier', 'reader', 'village_admin']);
    }

    // Accessors
    public function getRoleBadgeColorAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'danger',
            'village_admin' => 'primary',
            'cashier' => 'success',
            'reader' => 'warning',
        };
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Administrator',
            'village_admin' => 'Admin Desa',
            'cashier' => 'Kasir',
            'reader' => 'Pembaca Meter',
        };
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeForVillage($query, $villageId)
    {
        return $query->where('village_id', $villageId);
    }

    // Helper methods
    public function canManageVillage($villageId): bool
    {
        if ($this->role === 'admin') return true;
        return $this->village_id === $villageId;
    }

    public function canAccessBilling(): bool
    {
        return in_array($this->role, ['admin', 'village_admin', 'cashier']);
    }

    public function canReadMeters(): bool
    {
        return in_array($this->role, ['admin', 'village_admin', 'reader']);
    }

    public function canManageUsers(): bool
    {
        return $this->role === 'admin';
    }

    public function canAccessReports(): bool
    {
        return in_array($this->role, ['admin', 'village_admin']);
    }
}
