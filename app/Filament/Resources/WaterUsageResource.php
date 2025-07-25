<?php
// app/Filament/Resources/WaterUsageResource.php - Fixed with proper village context

namespace App\Filament\Resources;

use App\Filament\Resources\WaterUsageResource\Pages;
use App\Models\WaterUsage;
use App\Models\Customer;
use App\Models\BillingPeriod;
use App\Models\User;
use App\Models\Village;
use App\Traits\ExportableResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WaterUsageResource extends Resource
{
    use ExportableResource;

    protected static ?string $model = WaterUsage::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Pembacaan Meter';
    protected static ?string $modelLabel = 'Pembacaan Meter';
    protected static ?string $pluralModelLabel = 'Pembacaan Meter';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationGroup = 'Manajemen Data';

    // Role-based access control
    public static function canCreate(): bool
    {
        $user = User::find(Auth::user()->id);

        // Collectors cannot create water usage records
        return $user && !$user->isCollector();
    }

    public static function canEdit(Model $record): bool
    {
        $user = User::find(Auth::user()->id);

        // Collectors cannot edit water usage records
        return $user && !$user->isCollector();
    }

    public static function canDelete(Model $record): bool
    {
        $user = User::find(Auth::user()->id);

        // Only super_admin and village_admin can delete water usage records
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canDeleteAny(): bool
    {
        $user = User::find(Auth::user()->id);

        // Only super_admin and village_admin can bulk delete
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canViewAny(): bool
    {
        $user = User::find(Auth::user()->id);

        // All roles can view water usage records
        return $user && in_array($user->role, ['super_admin', 'village_admin', 'collector', 'operator']);
    }

    /**
     * Get the current village ID with better fallback logic
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

        // For village users (admin, collector, operator), try multiple approaches
        $villageId = $user->getCurrentVillageContext();

        // Log for debugging
        Log::info("WaterUsageResource: Getting village context for user", [
            'user_id' => $user->id,
            'role' => $user->role,
            'village_context' => $villageId,
            'config_village_id' => config('pamdes.current_village_id'),
            'tenant' => config('pamdes.tenant'),
            'user_villages' => $user->villages->pluck('id', 'name')->toArray(),
            'primary_village' => $user->getPrimaryVillageId(),
        ]);

        // If still no village, try direct fallbacks
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
            Log::warning("WaterUsageResource: User does not have access to village", [
                'user_id' => $user->id,
                'village_id' => $villageId,
            ]);

            // Fall back to first accessible village
            $firstVillage = $user->getAccessibleVillages()->first();
            $villageId = $firstVillage?->id;
        }

        return $villageId;
    }

    /**
     * Get village name for display
     */
    protected static function getVillageName(?string $villageId): string
    {
        if (!$villageId) {
            return 'No Village Selected';
        }

        // Try to get from config cache first
        $currentVillage = config('pamdes.current_village');
        if ($currentVillage && $currentVillage['id'] === $villageId) {
            return $currentVillage['name'];
        }

        // Fallback to database
        $village = Village::find($villageId);
        return $village?->name ?? 'Unknown Village';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['customer.village', 'billingPeriod', 'reader']);

        $user = User::find(Auth::user()->id);
        $currentVillage = static::getCurrentVillageId();

        if ($user?->isSuperAdmin() && $currentVillage) {
            $query->whereHas('customer', function ($q) use ($currentVillage) {
                $q->where('village_id', $currentVillage);
            });
        } elseif ($user?->isVillageAdmin() || $user?->isCollector() || $user?->role === 'operator') {
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            if ($accessibleVillages->isNotEmpty()) {
                $query->whereHas('customer', function ($q) use ($accessibleVillages) {
                    $q->whereIn('village_id', $accessibleVillages);
                });
            } else {
                // If no accessible villages, return empty result
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $user = User::find(Auth::user()->id);
        $currentVillageId = static::getCurrentVillageId();
        $villageName = static::getVillageName($currentVillageId);
        $isCollector = $user?->isCollector();

        // Debug logging
        Log::info("WaterUsageResource form - Village context", [
            'user_id' => $user?->id,
            'role' => $user?->role,
            'current_village_id' => $currentVillageId,
            'village_name' => $villageName,
            'is_collector' => $isCollector,
        ]);

        // Collectors get a read-only view
        if ($isCollector) {
            return $form
                ->schema([
                    Forms\Components\Section::make('Pembacaan Meter Air (Read-Only)')
                        ->description('Anda hanya dapat melihat data pembacaan meter')
                        ->schema([
                            Forms\Components\Placeholder::make('village_info')
                                ->label('Desa')
                                ->content(function (?WaterUsage $record) use ($villageName) {
                                    if ($record && $record->customer?->village) {
                                        return $record->customer->village->name;
                                    }
                                    return $villageName;
                                }),

                            Forms\Components\Placeholder::make('customer_info')
                                ->label('Pelanggan')
                                ->content(fn(?WaterUsage $record) => $record ?
                                    "{$record->customer->customer_code} - {$record->customer->name}" : '-'),

                            Forms\Components\Placeholder::make('period_info')
                                ->label('Periode Tagihan')
                                ->content(fn(?WaterUsage $record) => $record?->billingPeriod?->period_name ?? '-'),

                            Forms\Components\Placeholder::make('initial_meter')
                                ->label('Meter Awal')
                                ->content(fn(?WaterUsage $record) => number_format($record?->initial_meter ?? 0)),

                            Forms\Components\Placeholder::make('final_meter')
                                ->label('Meter Akhir')
                                ->content(fn(?WaterUsage $record) => number_format($record?->final_meter ?? 0)),

                            Forms\Components\Placeholder::make('total_usage_m3')
                                ->label('Total Pemakaian (m³)')
                                ->content(fn(?WaterUsage $record) => ($record?->total_usage_m3 ?? 0) . ' m³'),

                            Forms\Components\Placeholder::make('usage_date')
                                ->label('Tanggal Baca')
                                ->content(fn(?WaterUsage $record) => $record?->usage_date?->format('d/m/Y') ?? '-'),

                            Forms\Components\Placeholder::make('reader_info')
                                ->label('Petugas Baca')
                                ->content(function (?WaterUsage $record) {
                                    if ($record && $record->reader) {
                                        return $record->reader->name . ' (' . $record->reader->display_role . ')';
                                    }
                                    return 'Tidak ada data petugas';
                                }),

                            Forms\Components\Placeholder::make('notes')
                                ->label('Catatan')
                                ->content(fn(?WaterUsage $record) => $record?->notes ?? '-'),
                        ])
                        ->columns(3),
                ]);
        }

        // Full form for admin and operator roles
        return $form
            ->schema([
                Forms\Components\Section::make('Pembacaan Meter Air')
                    ->schema([
                        // Village selector for super admin, info for others  
                        $user?->isSuperAdmin() 
                            ? Forms\Components\Select::make('village_id')
                                ->label('Pilih Desa')
                                ->options(function () {
                                    return Village::where('is_active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id');
                                })
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    // Reset dependent fields when village changes
                                    $set('customer_id', null);
                                    $set('period_id', null);
                                    $set('initial_meter', 0);
                                    $set('final_meter', null);
                                    $set('total_usage_m3', 0);
                                })
                                ->helperText('Pilih desa untuk membuat pembacaan meter')
                                ->columnSpanFull()
                            : Forms\Components\Placeholder::make('village_info')
                                ->label('Desa')
                                ->content($villageName)
                                ->columnSpanFull(),

                        Forms\Components\Hidden::make('village_id')
                            ->default(function () use ($currentVillageId, $user) {
                                if ($user?->isSuperAdmin()) {
                                    return null; // Super admin selects manually
                                }

                                // For other roles, use current village context or fallback
                                if ($currentVillageId) {
                                    return $currentVillageId;
                                }

                                // Fallback to first accessible village for village admin/operator
                                $firstVillage = $user?->getAccessibleVillages()->first();
                                return $firstVillage?->id;
                            })
                            ->visible(fn() => !$user?->isSuperAdmin()),

                        Forms\Components\Select::make('customer_id')
                            ->label('Pelanggan')
                            ->options(function (Forms\Get $get) use ($currentVillageId, $user) {
                                // For super admin, use selected village_id from form
                                $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                
                                if (!$villageId) {
                                    return [];
                                }

                                return Customer::where('village_id', $villageId)
                                    ->where('status', 'active')
                                    ->get()
                                    ->mapWithKeys(function ($customer) {
                                        return [
                                            $customer->customer_id => "{$customer->customer_code} - {$customer->name}"
                                        ];
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state, Forms\Get $get) use ($currentVillageId, $user) {
                                $periodId = $get('period_id');
                                // For super admin, use selected village_id from form
                                $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                
                                if ($state && $periodId && $villageId) {
                                    $previousFinalMeter = WaterUsage::getPreviousMonthFinalMeter(
                                        $state, 
                                        $periodId, 
                                        $villageId
                                    );
                                    
                                    $set('initial_meter', $previousFinalMeter ?? 0);
                                    
                                    // Recalculate usage if final meter is already set
                                    $final = $get('final_meter') ?? 0;
                                    $initial = $previousFinalMeter ?? 0;
                                    $usage = max(0, $final - $initial);
                                    $set('total_usage_m3', $usage);
                                }
                            })
                            ->helperText(function (Forms\Get $get) use ($currentVillageId, $user) {
                                $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                return $villageId ? 'Pilih pelanggan dari desa yang sedang aktif' : ($user?->isSuperAdmin() ? 'Pilih desa terlebih dahulu' : 'Tidak ada desa yang tersedia');
                            }),

                        Forms\Components\Select::make('period_id')
                            ->label('Periode Tagihan')
                            ->options(function (Forms\Get $get) use ($currentVillageId, $user) {
                                // For super admin, use selected village_id from form
                                $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                
                                if (!$villageId) {
                                    return [];
                                }

                                return BillingPeriod::where('village_id', $villageId)
                                    ->orderBy('year', 'desc')
                                    ->orderBy('month', 'desc')
                                    ->get()
                                    ->pluck('period_name', 'period_id');
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state, Forms\Get $get) use ($currentVillageId, $user) {
                                $customerId = $get('customer_id');
                                // For super admin, use selected village_id from form
                                $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                
                                if ($state && $customerId && $villageId) {
                                    $previousFinalMeter = WaterUsage::getPreviousMonthFinalMeter(
                                        $customerId, 
                                        $state, 
                                        $villageId
                                    );
                                    
                                    $set('initial_meter', $previousFinalMeter ?? 0);
                                    
                                    // Recalculate usage if final meter is already set
                                    $final = $get('final_meter') ?? 0;
                                    $initial = $previousFinalMeter ?? 0;
                                    $usage = max(0, $final - $initial);
                                    $set('total_usage_m3', $usage);
                                }
                            })
                            ->helperText(function (Forms\Get $get) use ($currentVillageId, $user) {
                                $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                return $villageId ? 'Pilih periode tagihan yang sesuai' : ($user?->isSuperAdmin() ? 'Pilih desa terlebih dahulu' : 'Tidak ada periode yang tersedia');
                            }),

                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('initial_meter')
                                ->label('Meter Awal')
                                ->required()
                                ->numeric()
                                ->default(function (Forms\Get $get) use ($currentVillageId, $user) {
                                    $customerId = $get('customer_id');
                                    $periodId = $get('period_id');
                                    // For super admin, use selected village_id from form
                                    $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                    
                                    if ($customerId && $periodId && $villageId) {
                                        $previousFinalMeter = WaterUsage::getPreviousMonthFinalMeter(
                                            $customerId, 
                                            $periodId, 
                                            $villageId
                                        );
                                        
                                        return $previousFinalMeter ?? 0;
                                    }
                                    
                                    return 0;
                                })
                                ->live()
                                ->disabled(function (Forms\Get $get) use ($currentVillageId, $user) {
                                    $customerId = $get('customer_id');
                                    $periodId = $get('period_id');
                                    // For super admin, use selected village_id from form
                                    $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                    
                                    if ($customerId && $periodId && $villageId) {
                                        $previousFinalMeter = WaterUsage::getPreviousMonthFinalMeter(
                                            $customerId, 
                                            $periodId, 
                                            $villageId
                                        );
                                        
                                        // Disable if previous meter exists (not null)
                                        return $previousFinalMeter !== null;
                                    }
                                    
                                    return false;
                                })
                                ->afterStateUpdated(function (Forms\Set $set, $state, Forms\Get $get) {
                                    $final = $get('final_meter') ?? 0;
                                    $initial = $state ?? 0;
                                    $usage = max(0, $final - $initial);
                                    $set('total_usage_m3', $usage);
                                })
                                ->helperText(function (Forms\Get $get) use ($currentVillageId, $user) {
                                    $customerId = $get('customer_id');
                                    $periodId = $get('period_id');
                                    // For super admin, use selected village_id from form
                                    $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                    
                                    if ($customerId && $periodId && $villageId) {
                                        $previousFinalMeter = WaterUsage::getPreviousMonthFinalMeter(
                                            $customerId, 
                                            $periodId, 
                                            $villageId
                                        );
                                        
                                        if ($previousFinalMeter !== null) {
                                            return 'Meter awal otomatis diisi dari meter akhir bulan sebelumnya (' . number_format($previousFinalMeter) . ') dan tidak dapat diubah';
                                        }
                                    }
                                    
                                    return 'Angka meter pada awal periode (dapat diubah jika tidak ada data bulan sebelumnya)';
                                }),

                            Forms\Components\TextInput::make('final_meter')
                                ->label('Meter Akhir')
                                ->required()
                                ->numeric()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state, Forms\Get $get) {
                                    $initial = $get('initial_meter') ?? 0;
                                    $final = $state ?? 0;
                                    $usage = max(0, $final - $initial);
                                    $set('total_usage_m3', $usage);
                                })
                                ->rules([
                                    function (Forms\Get $get) {
                                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $initialMeter = $get('initial_meter') ?? 0;
                                            if ($value < $initialMeter) {
                                                $fail('Meter akhir tidak boleh lebih kecil dari meter awal (' . number_format($initialMeter) . ').');
                                            }
                                        };
                                    },
                                ])
                                ->helperText('Angka meter pada akhir periode'),
                        ])->columnSpanFull()->columns(2),

                        Forms\Components\TextInput::make('total_usage_m3')
                            ->label('Total Pemakaian (m³)')
                            ->numeric()
                            ->readOnly()
                            ->helperText('Otomatis dihitung dari selisih meter akhir dan awal'),

                        Forms\Components\DatePicker::make('usage_date')
                            ->label('Tanggal Baca')
                            ->required()
                            ->default(now())
                            ->helperText('Tanggal pembacaan meter dilakukan'),

                        // Reader field - read-only for operators, editable for admins
                        $user?->role === 'operator' 
                            ? Forms\Components\Placeholder::make('reader_info')
                                ->label('Petugas Baca')
                                ->content($user->name . ' (' . $user->display_role . ')')
                                ->helperText('Anda otomatis menjadi petugas baca untuk pembacaan ini')
                            : Forms\Components\Select::make('reader_id')
                                ->label('Petugas Baca')
                                ->options(function (Forms\Get $get) use ($currentVillageId, $user) {
                                    // For super admin, use selected village_id from form
                                    $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                    
                                    if (!$villageId) {
                                        return [];
                                    }

                                    // Get users who can read meters (ONLY operators)
                                    return User::whereHas('villages', function ($q) use ($villageId) {
                                        $q->where('villages.id', $villageId);
                                    })
                                        ->where('role', 'operator')
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(function ($reader) {
                                            return [
                                                $reader->id => $reader->name . ' (' . $reader->display_role . ')'
                                            ];
                                        });
                                })
                                ->searchable()
                                ->required()
                                ->helperText(function (Forms\Get $get) use ($currentVillageId, $user) {
                                    $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                    return $villageId ? 'Pilih petugas yang melakukan pembacaan meter' : ($user?->isSuperAdmin() ? 'Pilih desa terlebih dahulu' : 'Tidak ada operator yang tersedia');
                                }),

                        // Hidden field to store reader_id for operators
                        Forms\Components\Hidden::make('reader_id')
                            ->default(function () use ($user) {
                                return $user?->role === 'operator' ? $user->id : null;
                            })
                            ->visible($user?->role === 'operator'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Catatan tambahan tentang pembacaan meter (opsional)')
                            ->helperText('Misalnya: kondisi meter, kesulitan pembacaan, dll.'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = User::find(Auth::user()->id);
        $isSuperAdmin = $user?->isSuperAdmin();
        $isCollector = $user?->isCollector();
        $isOperator = $user?->role === 'operator';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.village.name')
                    ->label('Desa')
                    ->searchable()
                    ->sortable()
                    ->visible($isSuperAdmin)
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('customer.customer_code')
                    ->label('Kode Pelanggan')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Nama Pelanggan')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (WaterUsage $record): ?string {
                        return $record->customer->name;
                    }),

                Tables\Columns\TextColumn::make('billingPeriod.period_name')
                    ->label('Periode')
                    ->badge()
                    ->color('info')
                    ->sortable(['year', 'month']),

                Tables\Columns\TextColumn::make('initial_meter')
                    ->label('Meter Awal')
                    ->numeric()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('final_meter')
                    ->label('Meter Akhir')
                    ->numeric()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_usage_m3')
                    ->label('Pemakaian (m³)')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state <= 10 => 'success',
                        $state <= 25 => 'warning',
                        default => 'danger'
                    }),

                Tables\Columns\TextColumn::make('usage_date')
                    ->label('Tanggal Baca')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reader.name')
                    ->label('Petugas Baca')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function (?string $state, WaterUsage $record): string {
                        if ($record->reader) {
                            return $record->reader->name;
                        }
                        return 'Tidak ada';
                    })
                    ->badge()
                    ->color(fn($state) => $state === 'Tidak ada' ? 'gray' : 'success'),

                Tables\Columns\IconColumn::make('bill_exists')
                    ->label('Sudah Dibill')
                    ->boolean()
                    ->getStateUsing(fn(WaterUsage $record): bool => $record->bill !== null)
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(30)
                    ->tooltip(function (WaterUsage $record): ?string {
                        return $record->notes;
                    })
                    ->placeholder('Tidak ada catatan')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('village')
                    ->label('Desa')
                    ->relationship('customer.village', 'name')
                    ->visible($isSuperAdmin),

                Tables\Filters\SelectFilter::make('reader')
                    ->label('Petugas Baca')
                    ->relationship('reader', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('has_bill')
                    ->label('Sudah Dibill')
                    ->query(fn($query) => $query->whereHas('bill')),

                Tables\Filters\Filter::make('no_bill')
                    ->label('Belum Dibill')
                    ->query(fn($query) => $query->whereDoesntHave('bill')),

                Tables\Filters\Filter::make('usage_range')
                    ->form([
                        Forms\Components\TextInput::make('min_usage')
                            ->label('Pemakaian Minimum (m³)')
                            ->numeric(),
                        Forms\Components\TextInput::make('max_usage')
                            ->label('Pemakaian Maksimum (m³)')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_usage'],
                                fn(Builder $query, $usage): Builder => $query->where('total_usage_m3', '>=', $usage),
                            )
                            ->when(
                                $data['max_usage'],
                                fn(Builder $query, $usage): Builder => $query->where('total_usage_m3', '<=', $usage),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    // Edit only for non-collectors
                    Tables\Actions\EditAction::make()
                        ->visible(fn() => !$isCollector),

                    // Generate bill action - only for admin roles and collector and when no bill exists
                    Tables\Actions\Action::make('generate_bill')
                        ->label('Buat Tagihan')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->visible(
                            fn(WaterUsage $record): bool =>
                            $record->bill === null && !$isOperator
                        )
                        ->action(function (WaterUsage $record) {
                            try {
                                $bill = $record->generateBill([
                                    'admin_fee' => $record->customer->village?->getDefaultAdminFee() ?? 5000,
                                    'maintenance_fee' => $record->customer->village?->getDefaultMaintenanceFee() ?? 2000,
                                ]);

                                \Filament\Notifications\Notification::make()
                                    ->title('Tagihan berhasil dibuat')
                                    ->body("Tagihan untuk {$record->customer->customer_code} periode {$record->billingPeriod->period_name} telah dibuat dengan total Rp " . number_format($bill->total_amount))
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Gagal membuat tagihan')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    // View bill if exists
                    Tables\Actions\Action::make('view_bill')
                        ->label('Lihat Tagihan')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(fn(WaterUsage $record): bool => $record->bill !== null)
                        ->url(
                            fn(WaterUsage $record): string =>
                            route('filament.admin.resources.bills.edit', $record->bill->bill_id)
                        ),
                ])
            ])
            ->headerActions([
                // Export actions only for admin and operator roles
                ...($isCollector ? [] : static::getExportHeaderActions()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Bulk generate bills for admin roles
                    Tables\Actions\BulkAction::make('bulk_generate_bills')
                        ->label('Buat Tagihan Massal')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->visible(fn() => !$isCollector && !$isOperator)
                        ->action(function ($records) {
                            $generated = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if ($record->bill === null) {
                                    try {
                                        $record->generateBill([
                                            'admin_fee' => $record->customer->village?->getDefaultAdminFee() ?? 5000,
                                            'maintenance_fee' => $record->customer->village?->getDefaultMaintenanceFee() ?? 2000,
                                        ]);
                                        $generated++;
                                    } catch (\Exception $e) {
                                        $failed++;
                                    }
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Tagihan massal selesai')
                                ->body("Berhasil: {$generated}, Gagal: {$failed}")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Export actions only for admin and operator roles
                    ...($isCollector ? [] : static::getExportBulkActions()),
                ]),
            ])
            ->defaultSort('usage_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaterUsages::route('/'),
            'create' => Pages\CreateWaterUsage::route('/create'),
            'edit' => Pages\EditWaterUsage::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $currentVillage = static::getCurrentVillageId();

        if (!$currentVillage) {
            return null;
        }

        // Show count of unbilled water usages for current village
        $unbilledCount = static::getEloquentQuery()
            ->whereDoesntHave('bill')
            ->count();

        return $unbilledCount > 0 ? (string) $unbilledCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
