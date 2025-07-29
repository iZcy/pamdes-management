<?php
// app/Models/Village.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Village extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'slug',
        'description',
        'domain',
        'latitude',
        'longitude',
        'phone_number',
        'email',
        'address',
        'image_url',
        'settings',
        'is_active',
        'established_at',
        'pamdes_settings', // PAMDes-specific
        'sync_enabled',
        'last_synced_at',
    ];

    protected $casts = [
        'id' => 'string',
        'settings' => 'array',
        'pamdes_settings' => 'array',
        'is_active' => 'boolean',
        'sync_enabled' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'established_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    // Auto-generate UUID
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    // Relationships (updated foreign key references)
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'village_id', 'id');
    }

    public function billingPeriods(): HasMany
    {
        return $this->hasMany(BillingPeriod::class, 'village_id', 'id');
    }

    public function adminUsers(): HasMany
    {
        return $this->hasMany(User::class, 'village_id', 'id');
    }

    public function waterTariffs(): HasMany
    {
        return $this->hasMany(WaterTariff::class, 'village_id', 'id');
    }

    public function variables(): HasOne
    {
        return $this->hasOne(Variable::class, 'village_id', 'id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSyncEnabled($query)
    {
        return $query->where('sync_enabled', true);
    }

    public function scopeStale($query, $minutes = 30)
    {
        return $query->where(function ($q) use ($minutes) {
            $q->whereNull('last_synced_at')
                ->orWhere('last_synced_at', '<', now()->subMinutes($minutes));
        });
    }

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    // Accessors
    public function getIsDataFreshAttribute(): bool
    {
        return $this->last_synced_at &&
            $this->last_synced_at->gt(now()->subMinutes(30));
    }

    public function getFullNameAttribute(): string
    {
        return $this->name . ($this->description ? " - {$this->description}" : '');
    }

    public function getVillageIdAttribute(): string
    {
        // For backward compatibility with existing PAMDes code
        return $this->id;
    }

    // Helper methods for settings
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    // PAMDes-specific settings helpers
    public function getPamdesSetting(string $key, $default = null)
    {
        return data_get($this->pamdes_settings, $key, $default);
    }

    public function setPamdesSetting(string $key, $value): void
    {
        $settings = $this->pamdes_settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['pamdes_settings' => $settings]);
    }

    // Common PAMDes settings with defaults
    public function getDefaultAdminFee(): int
    {
        return $this->getPamdesSetting('default_admin_fee', 5000);
    }

    public function getDefaultMaintenanceFee(): int
    {
        return $this->getPamdesSetting('default_maintenance_fee', 2000);
    }

    // Get Contact
    public function getTel(): ?string
    {
        return "tel:" . preg_replace('/[^\d]/', '', $this->getSetting('phone_number') ?? $this->phone_number);
    }

    public function getWA(): ?string
    {
        $phone = $this->getSetting('phone_number') ?? $this->phone_number;
        return $phone ? "https://wa.me/" . preg_replace('/[^\d]/', '', $phone) : null;
    }

    public function isAutoGenerateBillsEnabled(): bool
    {
        return $this->getPamdesSetting('auto_generate_bills', true);
    }

    // Helper methods
    public function markAsSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }

    public function getCoordinates(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ];
        }
        return null;
    }

    // Logo-related helper methods
    public function getLogoUrl(): ?string
    {
        if ($this->image_url) {
            // If it's already a full URL, return as is
            if (filter_var($this->image_url, FILTER_VALIDATE_URL)) {
                return $this->image_url;
            }
            
            // Check if file exists before returning URL
            $filePath = storage_path('app/public/' . $this->image_url);
            if (file_exists($filePath)) {
                return asset('storage/' . $this->image_url);
            }
            
            // File doesn't exist, return null to use fallback
            return null;
        }
        
        return null;
    }

    public function getLogoPath(): ?string
    {
        return $this->image_url;
    }

    public function hasLogo(): bool
    {
        if (empty($this->image_url)) {
            return false;
        }
        
        // If it's a URL, assume it exists
        if (filter_var($this->image_url, FILTER_VALIDATE_URL)) {
            return true;
        }
        
        // Check if local file exists
        $filePath = storage_path('app/public/' . $this->image_url);
        return file_exists($filePath);
    }

    public function getLogoOrDefault(): string
    {
        return $this->getLogoUrl() ?? asset('images/logo.png');
    }

    public function getFaviconUrl(): string
    {
        // Use village logo as favicon if available, otherwise use default
        return $this->getLogoUrl() ?? asset('favicon.ico');
    }
}
