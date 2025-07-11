<?php
// app/Models/Collector.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collector extends Model
{
    use HasFactory;

    protected $primaryKey = 'collector_id';

    protected $fillable = [
        'village_id',
        'name',
        'normalized_name',
        'phone_number',
        'role',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'display_name',
    ];

    // Relationships
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'collector_id', 'collector_id');
    }

    // Accessors
    public function getDisplayNameAttribute(): string
    {
        $roleLabel = match ($this->role) {
            'collector' => 'Penagih',
            'kasir' => 'Kasir',
            'admin' => 'Admin',
            default => 'Unknown'
        };

        return $this->name . ' (' . $roleLabel . ')';
    }

    // Static methods
    public static function normalizeName(string $name): string
    {
        // Remove extra spaces, convert to lowercase, remove special characters
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return $normalized;
    }

    public static function findOrCreateCollector(string $villageId, string $name, array $additionalData = []): self
    {
        $normalizedName = self::normalizeName($name);

        // Try to find existing collector
        $collector = self::where('village_id', $villageId)
            ->where('normalized_name', $normalizedName)
            ->first();

        if ($collector) {
            // Update if additional data provided
            if (!empty($additionalData)) {
                $collector->update($additionalData);
            }
            return $collector;
        }

        // Create new collector
        return self::create(array_merge([
            'village_id' => $villageId,
            'name' => $name,
            'normalized_name' => $normalizedName,
            'is_active' => true,
        ], $additionalData));
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForVillage($query, string $villageId)
    {
        return $query->where('village_id', $villageId);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
