<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'village_id',
        'contact_info',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Filament User Interface
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
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

    // Helper methods
    public function canManageVillage($villageId): bool
    {
        return $this->village_id === $villageId || $this->village_id === null; // null means super admin
    }

    public function isSuperAdmin(): bool
    {
        return $this->village_id === null;
    }

    public function getVillageNameAttribute(): string
    {
        if ($this->village_id) {
            $village = Village::find($this->village_id);
            return $village['name'] ?? 'Unknown Village';
        }
        return 'All Villages';
    }
}
