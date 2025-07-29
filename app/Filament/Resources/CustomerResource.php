<?php
// app/Filament/Resources/CustomerResource.php - Fixed village context for operators

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
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

class CustomerResource extends Resource
{
    use ExportableResource;

    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Pelanggan';
    protected static ?string $modelLabel = 'Pelanggan';
    protected static ?string $pluralModelLabel = 'Pelanggan';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = 'Manajemen Data';

    // Role-based access control
    public static function canCreate(): bool
    {
        $user = User::find(Auth::user()->id);

        // Collectors cannot create customers, others can
        return $user && !$user->isCollector();
    }

    public static function canEdit(Model $record): bool
    {
        $user = User::find(Auth::user()->id);

        // Collectors cannot edit customers, others can
        return $user && !$user->isCollector();
    }

    public static function canDelete(Model $record): bool
    {
        $user = User::find(Auth::user()->id);

        // Only super_admin and village_admin can delete customers
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

        // All roles can view customers
        return $user && in_array($user->role, ['super_admin', 'village_admin', 'collector', 'operator']);
    }

    /**
     * Get the current village ID with better fallback logic for operators
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
        $query = parent::getEloquentQuery()->with('village');

        // For super admin, show all customers or filter by current village context
        $user = User::find(Auth::user()->id);
        if ($user?->isSuperAdmin()) {
            $currentVillage = static::getCurrentVillageId();
            if ($currentVillage) {
                $query->byVillage($currentVillage);
            }
        } else {
            // For village admin, collector, and operator - only show customers from their accessible villages
            $accessibleVillages = $user?->getAccessibleVillages()->pluck('id') ?? collect();
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
        $villageName = static::getVillageName($currentVillageId);
        $isCollector = $user?->isCollector();


        // Collectors get a read-only view
        if ($isCollector) {
            return $form
                ->schema([
                    Forms\Components\Section::make('Informasi Pelanggan (Read-Only)')
                        ->description('Anda hanya dapat melihat informasi pelanggan')
                        ->schema([
                            Forms\Components\Placeholder::make('village_display')
                                ->label('Desa')
                                ->content(function (?Customer $record) use ($currentVillageId, $villageName) {
                                    if ($record && $record->village) {
                                        return $record->village->name;
                                    }
                                    return $villageName;
                                }),

                            Forms\Components\Placeholder::make('customer_code')
                                ->label('Kode Pelanggan')
                                ->content(fn(?Customer $record) => $record?->customer_code ?? '-'),

                            Forms\Components\Placeholder::make('name')
                                ->label('Nama Lengkap')
                                ->content(fn(?Customer $record) => $record?->name ?? '-'),

                            Forms\Components\Placeholder::make('phone_number')
                                ->label('Nomor Telepon')
                                ->content(fn(?Customer $record) => $record?->phone_number ?? '-'),

                            Forms\Components\Placeholder::make('status')
                                ->label('Status')
                                ->content(fn(?Customer $record) => $record?->status === 'active' ? 'Aktif' : 'Tidak Aktif'),

                            Forms\Components\Placeholder::make('address')
                                ->label('Alamat Lengkap')
                                ->content(fn(?Customer $record) => $record?->full_address ?? '-'),
                        ])
                        ->columns(2),
                ]);
        }

        // Full form for admin and operator roles
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pelanggan')
                    ->schema([
                        Forms\Components\Select::make('village_id')
                            ->label('Desa')
                            ->options(function () {
                                return \App\Models\Village::where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->helperText('Pilih desa untuk pelanggan ini')
                            ->visible(fn() => $user?->isSuperAdmin()),

                        Forms\Components\Placeholder::make('village_name')
                            ->label('Desa')
                            ->content($villageName)
                            ->columnSpanFull(fn() => !$user?->isSuperAdmin())
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
                                
                                // Fallback to first accessible village for village admin/operator
                                $firstVillage = $user?->getAccessibleVillages()->first();
                                return $firstVillage?->id;
                            })
                            ->visible(fn() => !$user?->isSuperAdmin()),

                        Forms\Components\TextInput::make('customer_code')
                            ->label('Kode Pelanggan')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function (Forms\Get $get) use ($currentVillageId, $user) {
                                // For super admin, use selected village_id from form
                                $villageId = $user?->isSuperAdmin() ? $get('village_id') : $currentVillageId;
                                
                                // If no village yet, return empty and let it generate later
                                if (!$villageId) {
                                    return '';
                                }
                                
                                return Customer::generateCustomerCode($villageId);
                            })
                            ->maxLength(20),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone_number')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Aktif',
                                'inactive' => 'Tidak Aktif',
                            ])
                            ->searchable()
                            ->default('active')
                            ->required(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Forms\Components\Section::make('Alamat')
                    ->schema([
                        Forms\Components\Textarea::make('address')
                            ->label('Alamat Lengkap')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('rt')
                            ->label('RT')
                            ->maxLength(5),

                        Forms\Components\TextInput::make('rw')
                            ->label('RW')
                            ->maxLength(5),

                        Forms\Components\TextInput::make('village')
                            ->label('Dusun')
                            ->maxLength(100),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $user = User::find($user->id);
        $isSuperAdmin = $user?->isSuperAdmin();
        $isCollector = $user?->isCollector();
        $isOperator = $user?->role === 'operator';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('village.name')
                    ->label('Desa')
                    ->searchable()
                    ->sortable()
                    ->visible($isSuperAdmin)
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('customer_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Telepon')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('address')
                    ->label('Alamat')
                    ->searchable()
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('village')
                    ->label('Dusun')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('village_id')
                    ->label('Desa')
                    ->relationship('village', 'name')
                    ->visible($isSuperAdmin),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    ])
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    // Edit only for non-collectors
                    Tables\Actions\EditAction::make()
                        ->visible(fn() => !$isCollector),
                ])
            ])
            ->headerActions([
                // Export actions only for admin and operator roles
                ...($isCollector ? [] : static::getExportHeaderActions()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Export actions only for admin and operator roles
                    ...($isCollector ? [] : static::getExportBulkActions()),
                ]),
            ])
            ->defaultSort(
                'created_at',
                'desc'
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
