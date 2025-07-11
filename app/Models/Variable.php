<?php
// app/Models/Variable.php - Complete Variable model for PAMDes configuration

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Variable extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id',
        'tripay_use_main',
        'tripay_is_production',
        'tripay_api_key_prod',
        'tripay_private_key_prod',
        'tripay_merchant_code_prod',
        'tripay_api_key_dev',
        'tripay_private_key_dev',
        'tripay_merchant_code_dev',
        'tripay_timeout_minutes',
        'tripay_callback_url',
        'tripay_return_url',
        'other_settings',
    ];

    protected $casts = [
        'tripay_use_main' => 'boolean',
        'tripay_is_production' => 'boolean',
        'tripay_timeout_minutes' => 'integer',
        'other_settings' => 'array',
    ];

    // Relationships
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    // Accessors for encrypted fields
    public function getTripayApiKeyProdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getTripayPrivateKeyProdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getTripayMerchantCodeProdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getTripayApiKeyDevAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getTripayPrivateKeyDevAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getTripayMerchantCodeDevAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Mutators for encrypted fields
    public function setTripayApiKeyProdAttribute($value)
    {
        $this->attributes['tripay_api_key_prod'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setTripayPrivateKeyProdAttribute($value)
    {
        $this->attributes['tripay_private_key_prod'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setTripayMerchantCodeProdAttribute($value)
    {
        $this->attributes['tripay_merchant_code_prod'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setTripayApiKeyDevAttribute($value)
    {
        $this->attributes['tripay_api_key_dev'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setTripayPrivateKeyDevAttribute($value)
    {
        $this->attributes['tripay_private_key_dev'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setTripayMerchantCodeDevAttribute($value)
    {
        $this->attributes['tripay_merchant_code_dev'] = $value ? Crypt::encryptString($value) : null;
    }

    // Helper methods
    public function getTripayCredentials(): array
    {
        if ($this->tripay_use_main) {
            // Use main/global config from environment
            if ($this->tripay_is_production) {
                return [
                    'api_key' => config('tripay.api_key'),
                    'private_key' => config('tripay.private_key'),
                    'merchant_code' => config('tripay.merchant_code'),
                    'base_url' => config('tripay.base_url_production'),
                ];
            } else {
                return [
                    'api_key' => config('tripay.api_key_sb'),
                    'private_key' => config('tripay.private_key_sb'),
                    'merchant_code' => config('tripay.merchant_code_sb'),
                    'base_url' => config('tripay.base_url_sandbox'),
                ];
            }
        } else {
            // Use village-specific credentials
            if ($this->tripay_is_production) {
                return [
                    'api_key' => $this->tripay_api_key_prod,
                    'private_key' => $this->tripay_private_key_prod,
                    'merchant_code' => $this->tripay_merchant_code_prod,
                    'base_url' => config('tripay.base_url_production'),
                ];
            } else {
                return [
                    'api_key' => $this->tripay_api_key_dev,
                    'private_key' => $this->tripay_private_key_dev,
                    'merchant_code' => $this->tripay_merchant_code_dev,
                    'base_url' => config('tripay.base_url_sandbox'),
                ];
            }
        }
    }

    public function isConfigured(): bool
    {
        $credentials = $this->getTripayCredentials();
        return !empty($credentials['api_key']) &&
            !empty($credentials['private_key']) &&
            !empty($credentials['merchant_code']);
    }

    public function getCallbackUrl(): string
    {
        return $this->tripay_callback_url ?: route('tripay.callback');
    }

    public function getReturnUrl(): string
    {
        return $this->tripay_return_url ?: route('tripay.return');
    }

    public function getTimeoutMinutes(): int
    {
        return $this->tripay_timeout_minutes ?: 15;
    }

    // Scopes
    public function scopeForVillage($query, $villageId)
    {
        return $query->where('village_id', $villageId);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('village_id');
    }

    // Static helpers
    public static function getForVillage($villageId): ?Variable
    {
        return static::forVillage($villageId)->first();
    }

    public static function getGlobal(): ?Variable
    {
        return static::global()->first();
    }

    public static function getOrCreateForVillage($villageId): Variable
    {
        return static::firstOrCreate(
            ['village_id' => $villageId],
            [
                'tripay_use_main' => true,
                'tripay_is_production' => false,
                'tripay_timeout_minutes' => 15,
            ]
        );
    }
}
