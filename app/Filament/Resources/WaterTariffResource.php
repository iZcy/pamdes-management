<?php
// app/Filament/Resources/WaterTariffResource.php - Updated with smart range management

namespace App\Filament\Resources;

use App\Filament\Resources\WaterTariffResource\Pages;
use App\Models\WaterTariff;
use App\Models\User;
use App\Services\TariffRangeService;
use App\Traits\ExportableResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class WaterTariffResource extends Resource
{
    use ExportableResource;

    protected static ?string $model = WaterTariff::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Tarif Air';
    protected static ?string $modelLabel = 'Tarif Air';
    protected static ?string $pluralModelLabel = 'Tarif Air';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationGroup = 'Pengaturan';

    // Role-based navigation visibility
    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Only super admin and village admin can access tariff settings
        return $user?->isSuperAdmin() || $user?->role === 'village_admin';
    }

    // Role-based record access
    public static function canCreate(): bool
    {
        $user = User::find(Auth::user()->id);
        return $user?->isSuperAdmin() || $user?->role === 'village_admin';
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = User::find(Auth::user()->id);
        return $user?->isSuperAdmin() || $user?->role === 'village_admin';
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = User::find(Auth::user()->id);
        return $user?->isSuperAdmin() || $user?->role === 'village_admin';
    }

    public static function canDeleteAny(): bool
    {
        $user = User::find(Auth::user()->id);
        return $user?->isSuperAdmin() || $user?->role === 'village_admin';
    }

    /**
     * Get the current village ID with proper fallback logic
     */
    protected static function getCurrentVillageId(): ?string
    {
        $user = User::find(Auth::user()->id);

        if (!$user) {
            return null;
        }

        // For super admins, use village context if available
        if ($user->isSuperAdmin()) {
            return $user->getCurrentVillageContext();
        }

        // For village users, try multiple approaches
        $villageId = $user->getCurrentVillageContext();

        // If still no village, try fallbacks
        if (!$villageId) {
            // Try config directly
            $villageId = config('pamdes.current_village_id');

            if (!$villageId) {
                // Try primary village
                $villageId = $user->getPrimaryVillageId();
            }

            if (!$villageId) {
                // Try first accessible village
                $firstVillage = $user->getAccessibleVillages()->first();
                $villageId = $firstVillage?->id;
            }
        }

        // Verify user has access to this village
        if ($villageId && !$user->hasAccessToVillage($villageId)) {
            // Fall back to first accessible village
            $firstVillage = $user->getAccessibleVillages()->first();
            $villageId = $firstVillage?->id;
        }

        return $villageId;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('village');

        $user = User::find(Auth::user()->id);
        $currentVillage = static::getCurrentVillageId();

        if ($user?->isSuperAdmin() && $currentVillage) {
            $query->where('village_id', $currentVillage);
        } elseif ($user?->isVillageAdmin()) {
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            if ($accessibleVillages->isNotEmpty()) {
                $query->whereIn('village_id', $accessibleVillages);
            } else {
                // If no accessible villages, return empty result
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $user = Auth::user();
        $user = User::find($user->id);
        $currentVillageId = static::getCurrentVillageId();

        return $form
            ->schema([
                Forms\Components\Section::make('Pengaturan Tarif')
                    ->schema([
                        Forms\Components\Select::make('village_id')
                            ->label('Desa')
                            ->relationship('village', 'name')
                            ->default($currentVillageId)
                            ->required()
                            ->disabled(fn(?WaterTariff $record) => $record !== null) // Can't change village on edit
                            ->visible(fn() => $user?->isSuperAdmin())
                            ->live() // Make it reactive
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                // Trigger refresh of dependent sections
                                $set('_refresh_trigger', now()->toString());
                            }),

                        Forms\Components\Placeholder::make('village_display')
                            ->label('Desa')
                            ->content(function (?WaterTariff $record) use ($currentVillageId) {
                                if ($record && $record->village) {
                                    return $record->village->name;
                                }
                                if ($currentVillageId) {
                                    $village = \App\Models\Village::find($currentVillageId);
                                    return $village?->name ?? 'Unknown Village';
                                }
                                return 'No Village Selected';
                            })
                            ->visible(fn() => !$user?->isSuperAdmin()),

                        Forms\Components\Hidden::make('village_id')
                            ->default(function () use ($currentVillageId, $user) {
                                if ($user?->isSuperAdmin()) {
                                    return null; // Super admin selects manually
                                }

                                // For other roles, use current village context or fallback
                                if ($currentVillageId) {
                                    return $currentVillageId;
                                }

                                // Fallback to first accessible village for village admin
                                $firstVillage = $user?->getAccessibleVillages()->first();
                                return $firstVillage?->id;
                            })
                            ->visible(fn() => !$user?->isSuperAdmin()),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->live() // Make it reactive
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                $set('_refresh_trigger', now()->toString());
                            }),

                        // Smart field management based on context
                        Forms\Components\Group::make([
                            // For creating new tariff - only need minimum value
                            Forms\Components\TextInput::make('usage_min')
                                ->label('Pemakaian Minimum (m³)')
                                ->required(fn(string $context) => $context === 'create')
                                ->numeric()
                                ->minValue(0)
                                ->live(onBlur: true) // Add live validation
                                ->disabled(function (string $context, ?WaterTariff $record) {
                                    if ($context === 'create') return false;
                                    if (!$record) return true;

                                    $service = app(TariffRangeService::class);
                                    $editableFields = $service->getEditableFields($record);
                                    return !$editableFields['can_edit_min'];
                                })
                                ->afterStateUpdated(function (Forms\Set $set, $state, Forms\Get $get, string $context, ?WaterTariff $record) {
                                    $set('_refresh_trigger', now()->toString());
                                    
                                    // Live validation for minimum changes in edit mode
                                    if ($context === 'edit' && $record && $state !== null && $state != $record->usage_min) {
                                        try {
                                            $service = app(\App\Services\TariffRangeService::class);
                                            $existingTariffs = \App\Models\WaterTariff::where('village_id', $record->village_id)
                                                ->where('tariff_id', '!=', $record->tariff_id)
                                                ->orderBy('usage_min')
                                                ->get();
                                            
                                            // Test validation without actually saving
                                            $method = new \ReflectionMethod($service, 'validateMinUpdate');
                                            $method->setAccessible(true);
                                            $method->invoke($service, $existingTariffs, $record, (int)$state);
                                            
                                            $set('_preview_message', "✅ Dapat mengubah minimum ke {$state} m³");
                                        } catch (\Exception $e) {
                                            $set('_preview_message', "❌ {$e->getMessage()}");
                                        }
                                        return;
                                    }
                                    
                                    // Live preview for creation mode
                                    if ($context === 'create' && $state !== null && $state >= 0) {
                                        $villageId = $get('village_id') ?? config('pamdes.current_village_id');
                                        if ($villageId) {
                                            try {
                                                $service = app(TariffRangeService::class);
                                                $existingTariffs = $service->getVillageTariffs($villageId);

                                                // Check if this will split an existing range
                                                foreach ($existingTariffs as $tariff) {
                                                    if (
                                                        $state > $tariff['usage_min'] &&
                                                        ($tariff['usage_max'] === null || $state <= $tariff['usage_max'])
                                                    ) {

                                                        $originalRange = $tariff['range_display'];
                                                        $newRange1 = $tariff['usage_min'] . '-' . ($state - 1) . ' m³';
                                                        $newRange2 = $state . ($tariff['usage_max'] ? '-' . $tariff['usage_max'] : '+') . ' m³';

                                                        // Store preview message for helper text
                                                        $set('_preview_message', "Akan membagi rentang {$originalRange} menjadi [{$newRange1}] dan [{$newRange2}]");
                                                        return;
                                                    }
                                                }

                                                // Check if exact value exists
                                                foreach ($existingTariffs as $tariff) {
                                                    if ($tariff['usage_min'] == $state) {
                                                        $set('_preview_message', "⚠️ Rentang {$state} m³ sudah ada!");
                                                        return;
                                                    }
                                                }

                                                $set('_preview_message', "✅ Nilai {$state} m³ dapat ditambahkan");
                                            } catch (\Exception $e) {
                                                $set('_preview_message', "❌ Error: " . $e->getMessage());
                                            }
                                        }
                                    } else {
                                        $set('_preview_message', '');
                                    }
                                })
                                ->helperText(function (string $context, ?WaterTariff $record, Forms\Get $get) {
                                    // Show live preview message if available
                                    $previewMessage = $get('_preview_message');
                                    if ($previewMessage) {
                                        return $previewMessage;
                                    }

                                    if ($context === 'create') {
                                        $villageId = $get('village_id') ?? config('pamdes.current_village_id');
                                        if ($villageId) {
                                            $service = app(TariffRangeService::class);
                                            $suggestions = $service->getSuggestedRanges($villageId);

                                            if (!empty($suggestions)) {
                                                $suggestionText = "Saran: " . collect($suggestions)->take(2)->pluck('min')->map(fn($min) => $min . ' m³')->join(', ');
                                                return "Sistem akan otomatis membagi rentang yang ada bila diperlukan. {$suggestionText}";
                                            }
                                        }
                                        return 'Sistem akan otomatis membagi rentang yang ada bila diperlukan';
                                    }

                                    if (!$record) return '';

                                    $service = app(TariffRangeService::class);
                                    $editableFields = $service->getEditableFields($record);

                                    if ($editableFields['can_edit_min']) {
                                        return 'Mengubah nilai ini akan menyesuaikan rentang sebelumnya';
                                    }

                                    return 'Hanya rentang terakhir yang dapat mengedit minimum';
                                }),

                            // Hidden field to store preview message
                            Forms\Components\Hidden::make('_preview_message'),

                            // Hidden field to trigger refresh
                            Forms\Components\Hidden::make('_refresh_trigger'),
                            
                            // Hidden field to store validation errors
                            Forms\Components\Hidden::make('_validation_error'),

                            Forms\Components\TextInput::make('usage_max')
                                ->label('Pemakaian Maksimum (m³)')
                                ->numeric()
                                ->minValue(0)
                                ->live(onBlur: true) // Make it reactive
                                ->disabled(function (string $context, ?WaterTariff $record) {
                                    if ($context === 'create') return true;
                                    if (!$record) return true;

                                    $service = app(TariffRangeService::class);
                                    $editableFields = $service->getEditableFields($record);
                                    return !$editableFields['can_edit_max'];
                                })
                                ->visible(fn(string $context) => $context === 'edit')
                                ->afterStateUpdated(function (Forms\Set $set, $state, Forms\Get $get, ?WaterTariff $record) {
                                    $set('_refresh_trigger', now()->toString());
                                    
                                    // Live validation for maximum changes
                                    if ($record && $state !== null && $state != $record->usage_max) {
                                        try {
                                            $service = app(\App\Services\TariffRangeService::class);
                                            $existingTariffs = \App\Models\WaterTariff::where('village_id', $record->village_id)
                                                ->where('tariff_id', '!=', $record->tariff_id)
                                                ->orderBy('usage_min')
                                                ->get();
                                            
                                            // Test validation without actually saving
                                            $method = new \ReflectionMethod($service, 'validateMaxUpdate');
                                            $method->setAccessible(true);
                                            $method->invoke($service, $existingTariffs, $record, (int)$state);
                                            
                                            $set('_validation_error', '');
                                        } catch (\Exception $e) {
                                            $set('_validation_error', $e->getMessage());
                                        }
                                    } else {
                                        $set('_validation_error', '');
                                    }
                                })
                                ->helperText(function (?WaterTariff $record, Forms\Get $get) {
                                    // Show validation error if present
                                    $validationError = $get('_validation_error');
                                    if ($validationError) {
                                        return "❌ {$validationError}";
                                    }
                                    
                                    if (!$record) return '';

                                    $service = app(TariffRangeService::class);
                                    $editableFields = $service->getEditableFields($record);

                                    if ($editableFields['is_last_range']) {
                                        return 'Rentang terakhir (unlimited) - tidak dapat diubah';
                                    }

                                    return 'Mengubah nilai ini akan menyesuaikan rentang berikutnya';
                                }),
                        ])
                            ->columns(2)
                            ->columnSpan(2),

                        Forms\Components\Group::make([
                            Forms\Components\Placeholder::make('current_range')
                                ->label('Rentang Saat Ini')
                                ->content(function (?WaterTariff $record, Forms\Get $get) {
                                    if (!$record) return 'Akan dibuat otomatis';

                                    // Get current form values for dynamic preview
                                    $usageMin = $get('usage_min') ?? $record->usage_min;
                                    $usageMax = $get('usage_max') ?? $record->usage_max;

                                    if ($usageMax === null) {
                                        return $usageMin . '+ m³';
                                    }

                                    return $usageMin . '-' . $usageMax . ' m³';
                                })
                                ->visible(fn(string $context, ?WaterTariff $record) => $context === 'edit' && $record),

                            Forms\Components\TextInput::make('price_per_m3')
                                ->label('Harga per m³')
                                ->required()
                                ->numeric()
                                ->prefix('Rp')
                                ->minValue(0)
                                ->live(onBlur: true) // Make it reactive
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    $set('_refresh_trigger', now()->toString());
                                })
                                ->helperText('Harga dapat selalu diubah'),
                        ])->columnSpan(2),
                    ])->columns([
                        'default' => 1,
                        'lg' => 2,
                    ]),

                // Show existing tariffs for context
                Forms\Components\Section::make('Tarif Saat Ini')
                    ->schema([
                        Forms\Components\Placeholder::make('existing_tariffs')
                            ->label('')
                            ->content(function (?WaterTariff $record, Forms\Get $get) {
                                $villageId = $record?->village_id ?? $get('village_id') ?? config('pamdes.current_village_id');
                                $refreshTrigger = $get('_refresh_trigger'); // This will trigger refresh when changed

                                if (!$villageId) return 'Pilih desa terlebih dahulu';

                                try {
                                    $service = app(TariffRangeService::class);
                                    $tariffs = $service->getVillageTariffs($villageId);

                                    // For create context, simulate adding new tariff
                                    if (!$record && $get('usage_min') && $get('price_per_m3')) {
                                        $newMin = (int) $get('usage_min');
                                        $newPrice = (float) $get('price_per_m3');

                                        // Simulate the range splitting/adjustment
                                        $updatedTariffs = [];
                                        $newTariffAdded = false;

                                        foreach ($tariffs as $tariff) {
                                            if (
                                                $newMin > $tariff['usage_min'] &&
                                                ($tariff['usage_max'] === null || $newMin <= $tariff['usage_max'])
                                            ) {
                                                // Split existing range - first part
                                                $updatedTariffs[] = [
                                                    'usage_min' => $tariff['usage_min'],
                                                    'usage_max' => $newMin - 1,
                                                    'price_per_m3' => $tariff['price_per_m3'],
                                                    'range_display' => $tariff['usage_min'] . '-' . ($newMin - 1) . ' m³',
                                                    'editable_fields' => $tariff['editable_fields'],
                                                ];

                                                // Add new tariff
                                                $updatedTariffs[] = [
                                                    'usage_min' => $newMin,
                                                    'usage_max' => $tariff['usage_max'],
                                                    'price_per_m3' => $newPrice,
                                                    'range_display' => $newMin . ($tariff['usage_max'] ? '-' . $tariff['usage_max'] : '+') . ' m³',
                                                    'editable_fields' => [
                                                        'can_edit_min' => true,
                                                        'can_edit_max' => $tariff['usage_max'] !== null,
                                                    ],
                                                    'is_preview' => true,
                                                ];
                                                $newTariffAdded = true;
                                            } else {
                                                $updatedTariffs[] = $tariff;
                                            }
                                        }

                                        // If new tariff wasn't added (new minimum is larger than all existing), add it at the end
                                        if (!$newTariffAdded) {
                                            $updatedTariffs[] = [
                                                'usage_min' => $newMin,
                                                'usage_max' => null,
                                                'price_per_m3' => $newPrice,
                                                'range_display' => $newMin . '+ m³',
                                                'editable_fields' => [
                                                    'can_edit_min' => true,
                                                    'can_edit_max' => false,
                                                ],
                                                'is_preview' => true,
                                            ];
                                        }

                                        $tariffs = $updatedTariffs;
                                    }

                                    // For edit context, update the current record values with form data and show auto-adjustments
                                    if ($record) {
                                        $formUsageMin = $get('usage_min');
                                        $formUsageMax = $get('usage_max');
                                        $formPrice = $get('price_per_m3');

                                        // Check if there are actual changes
                                        $hasMinChange = $formUsageMin !== null && $formUsageMin != $record->usage_min;
                                        $hasMaxChange = $formUsageMax !== null && $formUsageMax != $record->usage_max;
                                        $hasPriceChange = $formPrice !== null && $formPrice != $record->price_per_m3;

                                        if ($hasMinChange || $hasMaxChange || $hasPriceChange) {
                                            $recordUpdated = false;

                                            foreach ($tariffs as $index => &$tariff) {
                                                // Update the current record being edited
                                                if ($tariff['usage_min'] == $record->usage_min && !$recordUpdated) {
                                                    if ($hasMinChange) $tariff['usage_min'] = (int) $formUsageMin;
                                                    if ($hasMaxChange) $tariff['usage_max'] = (int) $formUsageMax;
                                                    if ($hasPriceChange) $tariff['price_per_m3'] = (float) $formPrice;

                                                    // Update range display
                                                    if ($tariff['usage_max'] === null) {
                                                        $tariff['range_display'] = $tariff['usage_min'] . '+ m³';
                                                    } else {
                                                        $tariff['range_display'] = $tariff['usage_min'] . '-' . $tariff['usage_max'] . ' m³';
                                                    }
                                                    $tariff['is_preview'] = true;
                                                    $recordUpdated = true;

                                                    // If max was changed, auto-adjust the next range
                                                    if ($hasMaxChange && isset($tariffs[$index + 1])) {
                                                        $nextTariff = &$tariffs[$index + 1];
                                                        $newNextMin = $tariff['usage_max'] + 1;
                                                        
                                                        // Only adjust if the change would affect the next range
                                                        if ($newNextMin != $nextTariff['usage_min']) {
                                                            $nextTariff['usage_min'] = $newNextMin;
                                                            
                                                            // Update next tariff's range display
                                                            if ($nextTariff['usage_max'] === null) {
                                                                $nextTariff['range_display'] = $nextTariff['usage_min'] . '+ m³';
                                                            } else {
                                                                $nextTariff['range_display'] = $nextTariff['usage_min'] . '-' . $nextTariff['usage_max'] . ' m³';
                                                            }
                                                            $nextTariff['is_preview'] = true;
                                                            $nextTariff['auto_adjusted'] = true;
                                                        }
                                                    }

                                                    break;
                                                }
                                            }
                                            unset($tariff); // Clean up reference
                                        }
                                    }

                                    if (empty($tariffs)) {
                                        return '<div class="text-gray-500 italic">Belum ada tarif untuk desa ini</div>';
                                    }

                                    // Sort tariffs by usage_min to ensure proper order
                                    usort($tariffs, function ($a, $b) {
                                        return $a['usage_min'] <=> $b['usage_min'];
                                    });

                                    $content = '<div class="space-y-2">';
                                    foreach ($tariffs as $tariff) {
                                        $editableInfo = [];
                                        if ($tariff['editable_fields']['can_edit_min']) $editableInfo[] = 'min';
                                        if ($tariff['editable_fields']['can_edit_max']) $editableInfo[] = 'max';
                                        $editableText = !empty($editableInfo) ? ' <span class="text-xs text-blue-600">(dapat edit: ' . implode(', ', $editableInfo) . ')</span>' : '';

                                        // Different styling for different types of changes
                                        if (isset($tariff['auto_adjusted'])) {
                                            $previewClass = 'border-orange-400 bg-orange-50';
                                            $previewLabel = ' <span class="text-xs text-orange-600 font-bold">(AUTO-ADJUSTED)</span>';
                                        } elseif (isset($tariff['is_preview'])) {
                                            $previewClass = 'border-blue-500 bg-blue-50';
                                            $previewLabel = ' <span class="text-xs text-blue-600 font-bold">(EDITED)</span>';
                                        } else {
                                            $previewClass = 'border-gray-200';
                                            $previewLabel = '';
                                        }

                                        $content .= '<div class="flex justify-between items-center p-3 rounded-lg border ' . $previewClass . '">';
                                        $content .= '<span class="font-medium text-gray-900">' . $tariff['range_display'] . $previewLabel . '</span>';
                                        $content .= '<span class="text-green-600 font-semibold">Rp ' . number_format($tariff['price_per_m3']) . '/m³' . $editableText . '</span>';
                                        $content .= '</div>';
                                    }
                                    $content .= '</div>';

                                    return new \Illuminate\Support\HtmlString($content);
                                } catch (\Exception $e) {
                                    return '<div class="text-red-500">Error: ' . $e->getMessage() . '</div>';
                                }
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // Show example calculations
                Forms\Components\Section::make('Contoh Perhitungan')
                    ->schema([
                        Forms\Components\Placeholder::make('calculations')
                            ->label('')
                            ->content(function (?WaterTariff $record, Forms\Get $get) {
                                $villageId = $record?->village_id ?? $get('village_id') ?? config('pamdes.current_village_id');
                                $refreshTrigger = $get('_refresh_trigger'); // This will trigger refresh when changed

                                if (!$villageId) return 'Pilih desa untuk melihat contoh perhitungan';

                                try {
                                    // Get existing tariffs to generate relevant examples
                                    $service = app(\App\Services\TariffRangeService::class);
                                    $existingTariffs = $service->getVillageTariffs($villageId);

                                    // Create temporary tariff structure for calculation with form values
                                    $tempTariffs = [];
                                    foreach ($existingTariffs as $tariff) {
                                        $tempTariffs[] = [
                                            'usage_min' => $tariff['usage_min'],
                                            'usage_max' => $tariff['usage_max'],
                                            'price_per_m3' => $tariff['price_per_m3'],
                                        ];
                                    }

                                    // Apply form changes for preview with auto-adjustments
                                    if ($record) {
                                        $formUsageMax = $get('usage_max');
                                        $hasMaxChange = $formUsageMax !== null && $formUsageMax != $record->usage_max;
                                        
                                        // Update existing record and handle auto-adjustments
                                        foreach ($tempTariffs as $index => &$tariff) {
                                            if ($tariff['usage_min'] == $record->usage_min) {
                                                $tariff['usage_min'] = (int) ($get('usage_min') ?? $record->usage_min);
                                                if ($hasMaxChange) $tariff['usage_max'] = (int) $formUsageMax;
                                                $tariff['price_per_m3'] = (float) ($get('price_per_m3') ?? $record->price_per_m3);
                                                
                                                // If max was changed, auto-adjust next range
                                                if ($hasMaxChange && isset($tempTariffs[$index + 1])) {
                                                    $tempTariffs[$index + 1]['usage_min'] = $tariff['usage_max'] + 1;
                                                }
                                                break;
                                            }
                                        }
                                    } else if ($get('usage_min') && $get('price_per_m3')) {
                                        // Add new tariff for preview calculation
                                        $newMin = (int) $get('usage_min');
                                        $newPrice = (float) $get('price_per_m3');

                                        // Handle range splitting logic for calculation with gap validation
                                        $updatedTariffs = [];
                                        $newTariffAdded = false;

                                        foreach ($tempTariffs as $tariff) {
                                            if (
                                                $newMin > $tariff['usage_min'] &&
                                                ($tariff['usage_max'] === null || $newMin <= $tariff['usage_max'])
                                            ) {
                                                // Only split if there's room for proper gap (at least 2 units difference)
                                                if ($newMin > $tariff['usage_min'] + 1) {
                                                    // Split existing range - first part ends at newMin - 1
                                                    $updatedTariffs[] = [
                                                        'usage_min' => $tariff['usage_min'],
                                                        'usage_max' => $newMin - 1,
                                                        'price_per_m3' => $tariff['price_per_m3'],
                                                    ];
                                                }

                                                // Add new tariff with correct range
                                                $updatedTariffs[] = [
                                                    'usage_min' => $newMin,
                                                    'usage_max' => $tariff['usage_max'],
                                                    'price_per_m3' => $newPrice,
                                                ];
                                                $newTariffAdded = true;
                                            } else {
                                                $updatedTariffs[] = $tariff;
                                            }
                                        }

                                        // If new tariff wasn't added, find correct insertion point with gap validation
                                        if (!$newTariffAdded) {
                                            $inserted = false;
                                            $finalTariffs = [];
                                            
                                            foreach ($updatedTariffs as $tariff) {
                                                if (!$inserted && $newMin < $tariff['usage_min']) {
                                                    // Ensure gap between ranges
                                                    $newMax = $tariff['usage_min'] - 1;
                                                    if ($newMax >= $newMin) {
                                                        $finalTariffs[] = [
                                                            'usage_min' => $newMin,
                                                            'usage_max' => $newMax,
                                                            'price_per_m3' => $newPrice,
                                                        ];
                                                        $inserted = true;
                                                    }
                                                }
                                                $finalTariffs[] = $tariff;
                                            }
                                            
                                            // If still not inserted, add at the end (infinite range)
                                            if (!$inserted) {
                                                $finalTariffs[] = [
                                                    'usage_min' => $newMin,
                                                    'usage_max' => null,
                                                    'price_per_m3' => $newPrice,
                                                ];
                                            }
                                            
                                            $updatedTariffs = $finalTariffs;
                                        }

                                        $tempTariffs = $updatedTariffs;
                                    }

                                    $content = '<div class="space-y-3">';
                                    $content .= '<div class="text-sm text-transparent mb-3">Contoh perhitungan biaya air berdasarkan rentang tarif:</div>';

                                    // Generate examples based on tariff ranges
                                    $usageExamples = [];

                                    if (empty($tempTariffs)) {
                                        // Default examples if no tariffs exist
                                        $usageExamples = [5, 15, 25, 35, 50];
                                    } else {
                                        // Sort tariffs to ensure proper order
                                        usort($tempTariffs, function ($a, $b) {
                                            return $a['usage_min'] <=> $b['usage_min'];
                                        });

                                        // Generate examples to cover all ranges
                                        foreach ($tempTariffs as $index => $tariff) {
                                            $min = $tariff['usage_min'];
                                            $max = $tariff['usage_max'];

                                            // Add example from middle of each range
                                            if ($max !== null) {
                                                // For finite ranges, add middle point
                                                $midpoint = intval(($min + $max) / 2);
                                                $usageExamples[] = $midpoint;

                                                // If range is wide enough, also add examples near boundaries
                                                if (($max - $min) >= 10) {
                                                    $usageExamples[] = $min + 2; // Near start
                                                    $usageExamples[] = $max - 2; // Near end
                                                }
                                            } else {
                                                // For infinite ranges (last tier), add a few examples
                                                $usageExamples[] = $min + 5;
                                                $usageExamples[] = $min + 15;
                                                if ($index === count($tempTariffs) - 1) {
                                                    // Only add higher examples for the last tier
                                                    $usageExamples[] = $min + 30;
                                                }
                                            }
                                        }

                                        // Add one example that spans multiple tiers (higher usage)
                                        if (count($tempTariffs) > 1) {
                                            $lastTariff = end($tempTariffs);
                                            $highUsage = $lastTariff['usage_min'] + 20;
                                            $usageExamples[] = $highUsage;
                                        }

                                        // Remove duplicates and sort
                                        $usageExamples = array_unique($usageExamples);
                                        sort($usageExamples);

                                        // Ensure we don't have too many examples (limit to 8)
                                        $usageExamples = array_slice($usageExamples, 0, 8);
                                    }

                                    foreach ($usageExamples as $usage) {
                                        try {
                                            // Calculate using temporary tariff structure with proper tiered pricing
                                            $totalCharge = 0;
                                            $breakdown = [];

                                            // Sort tariffs by usage_min to ensure proper calculation order
                                            usort($tempTariffs, function ($a, $b) {
                                                return $a['usage_min'] <=> $b['usage_min'];
                                            });

                                            $remainingUsage = $usage;

                                            foreach ($tempTariffs as $tariff) {
                                                if ($remainingUsage <= 0) break;

                                                $tierMin = $tariff['usage_min'];
                                                $tierMax = $tariff['usage_max'];
                                                $rate = $tariff['price_per_m3'];

                                                // Calculate usage that falls within this tier
                                                if ($usage >= $tierMin) {
                                                    // How much of the total usage falls in this tier?
                                                    $tierStart = $tierMin;
                                                    $tierEnd = $tierMax ?? $usage; // If no max, use total usage

                                                    // Usage in this tier is the overlap between [tierStart, tierEnd] and [1, usage]
                                                    $usageStart = max($tierStart, 1);
                                                    $usageEnd = min($tierEnd, $usage);

                                                    if ($usageEnd >= $usageStart) {
                                                        $usageInThisTier = $usageEnd - $usageStart + 1;
                                                        $tierCharge = $usageInThisTier * $rate;
                                                        $totalCharge += $tierCharge;

                                                        $breakdown[] = [
                                                            'usage' => $usageInThisTier,
                                                            'rate' => $rate,
                                                            'charge' => $tierCharge,
                                                            'range' => $tierMax === null ? $tierMin . '+' : $tierMin . '-' . $tierMax
                                                        ];
                                                    }
                                                }
                                            }

                                            $breakdownText = [];
                                            foreach ($breakdown as $tier) {
                                                $breakdownText[] = "{$tier['usage']} m³ × Rp" . number_format($tier['rate']);
                                            }

                                            // Determine which range this usage falls into
                                            $rangeInfo = '';
                                            foreach ($tempTariffs as $tariff) {
                                                if (
                                                    $usage >= $tariff['usage_min'] &&
                                                    ($tariff['usage_max'] === null || $usage <= $tariff['usage_max'])
                                                ) {
                                                    $rangeDisplay = $tariff['usage_max'] === null ?
                                                        $tariff['usage_min'] . '+ m³' :
                                                        $tariff['usage_min'] . '-' . $tariff['usage_max'] . ' m³';
                                                    $rangeInfo = " ({$rangeDisplay})";
                                                    break;
                                                }
                                            }

                                            $content .= '<div class="flex justify-between items-center p-2 bg-transparent rounded">';
                                            $content .= '<span class="font-medium">' . $usage . ' m³' . $rangeInfo . ':</span>';
                                            $content .= '<span class="text-sm text-transparent">' . implode(' + ', $breakdownText) . '</span>';
                                            $content .= '<span class="font-semibold text-green-600">Rp ' . number_format($totalCharge) . '</span>';
                                            $content .= '</div>';
                                        } catch (\Exception $e) {
                                            $content .= '<div class="flex justify-between items-center p-2 bg-red-50 rounded">';
                                            $content .= '<span class="font-medium">' . $usage . ' m³:</span>';
                                            $content .= '<span class="text-red-600 text-sm">Error: ' . $e->getMessage() . '</span>';
                                            $content .= '</div>';
                                        }
                                    }
                                    $content .= '</div>';

                                    return new \Illuminate\Support\HtmlString($content);
                                } catch (\Exception $e) {
                                    return '<div class="text-red-500">Error: ' . $e->getMessage() . '</div>';
                                }
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $user = User::find($user->id);
        $isSuperAdmin = $user?->isSuperAdmin();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('village.name')
                    ->label('Desa')
                    ->searchable()
                    ->sortable()
                    ->visible($isSuperAdmin)
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('usage_range')
                    ->label('Rentang Pemakaian')
                    ->getStateUsing(function (WaterTariff $record): string {
                        return $record->usage_range;
                    })
                    ->sortable(['usage_min', 'usage_max']),

                Tables\Columns\TextColumn::make('price_per_m3')
                    ->label('Harga per m³')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('editable_info')
                    ->label('Dapat Edit')
                    ->getStateUsing(function (WaterTariff $record): string {
                        $fields = app(TariffRangeService::class)->getEditableFields($record);
                        $editable = [];
                        if ($fields['can_edit_min']) $editable[] = 'Min';
                        if ($fields['can_edit_max']) $editable[] = 'Max';
                        $editable[] = 'Harga';
                        return implode(', ', $editable);
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('village_id')
                    ->label('Desa')
                    ->relationship('village', 'name')
                    ->visible($isSuperAdmin),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->mutateFormDataUsing(function (array $data, WaterTariff $record): array {
                            // Handle smart updates using the service
                            try {
                                $service = app(TariffRangeService::class);
                                $fields = $service->getEditableFields($record);

                                $newMin = $fields['can_edit_min'] && isset($data['usage_min']) ? $data['usage_min'] : null;
                                $newMax = $fields['can_edit_max'] && isset($data['usage_max']) ? $data['usage_max'] : null;
                                $newPrice = isset($data['price_per_m3']) ? $data['price_per_m3'] : null;

                                $service->updateTariffRange($record, $newMax, $newMin, $newPrice);

                                Notification::make()
                                    ->title('Tarif berhasil diperbarui')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Gagal memperbarui tarif')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                throw $e;
                            }

                            return $data;
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->action(function (WaterTariff $record) {
                            try {
                                app(TariffRangeService::class)->deleteTariffRange($record);

                                Notification::make()
                                    ->title('Tarif berhasil dihapus')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Gagal menghapus tarif')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
            ])
            ->headerActions([
                ...static::getExportHeaderActions(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $service = app(TariffRangeService::class);
                            foreach ($records as $record) {
                                $service->deleteTariffRange($record);
                            }
                        }),
                    ...static::getExportBulkActions(),
                ]),
            ])
            ->defaultSort('usage_min');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaterTariffs::route('/'),
            'create' => Pages\CreateWaterTariff::route('/create'),
            'edit' => Pages\EditWaterTariff::route('/{record}/edit'),
        ];
    }
}
