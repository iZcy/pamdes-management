<?php
// app/Filament/Resources/UserResource.php - Updated to allow village admin management

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Village;
use App\Traits\ExportableResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    use ExportableResource;

    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Kelola Admin';
    protected static ?string $modelLabel = 'Admin';
    protected static ?string $pluralModelLabel = 'Admin';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationGroup = 'Sistem';

    // Role-based navigation visibility
    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Super admin and village admin can see user management
        return $user && in_array($user->role, ['super_admin', 'village_admin']) ?? false;
    }

    // Role-based access control
    public static function canCreate(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Super admin and village admin can create users
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        if (!$user) return false;

        // Super admin can edit all users
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Village admin can only edit operators and collectors in their villages
        if ($user->role === 'village_admin') {
            // Cannot edit super admins or other village admins
            if (in_array($record->role, ['super_admin', 'village_admin'])) {
                return false;
            }

            // Can only edit users in their accessible villages
            $userVillages = $user->getAccessibleVillages()->pluck('id');
            $recordVillages = $record->getAccessibleVillages()->pluck('id');

            return $userVillages->intersect($recordVillages)->isNotEmpty();
        }

        return false;
    }

    public static function canDelete(Model $record): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        if (!$user) return false;

        // Super admin logic
        if ($user->isSuperAdmin()) {
            // Prevent deleting the last super admin
            if ($record->isSuperAdmin() && User::superAdmins()->count() <= 1) {
                return false;
            }
            return true;
        }

        // Village admin can only delete operators and collectors in their villages
        if ($user->role === 'village_admin') {
            // Cannot delete super admins or other village admins
            if (in_array($record->role, ['super_admin', 'village_admin'])) {
                return false;
            }

            // Can only delete users in their accessible villages
            $userVillages = $user->getAccessibleVillages()->pluck('id');
            $recordVillages = $record->getAccessibleVillages()->pluck('id');

            return $userVillages->intersect($recordVillages)->isNotEmpty();
        }

        return false;
    }

    public static function canDeleteAny(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Super admin and village admin can view users
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $user = User::find($user->id);

        if (!$user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        // Super admin can see all users
        if ($user->isSuperAdmin()) {
            return parent::getEloquentQuery();
        }

        // Village admin can only see users in their villages (plus operators/collectors)
        if ($user->role === 'village_admin') {
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');

            return parent::getEloquentQuery()->where(function ($query) use ($accessibleVillages, $user) {
                // Show operators and collectors in their villages
                $query->whereHas('villages', function ($q) use ($accessibleVillages) {
                    $q->whereIn('villages.id', $accessibleVillages);
                })->whereIn('role', ['operator', 'collector']);

                // Also show themselves
                $query->orWhere('id', $user->id);
            });
        }

        // Return empty query for other roles
        return parent::getEloquentQuery()->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        $user = Auth::user();
        $user = User::find($user->id);

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pengguna')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        // Email Selection with Great UX
                        Forms\Components\Section::make('Pengaturan Email')
                            ->description('Pilih apakah akan menggunakan email desa atau email pribadi untuk menerima notifikasi dan komunikasi dari sistem PAMDes.')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                        
                        Forms\Components\Group::make([
                            Forms\Components\Radio::make('email_choice')
                                ->label('Pilihan Email')
                                ->options([
                                    'village' => 'Gunakan Email Desa',
                                    'own' => 'Gunakan Email Pribadi',
                                ])
                                ->descriptions([
                                    'village' => 'Email desa akan digunakan untuk notifikasi',
                                    'own' => 'Masukkan email pribadi Anda',
                                ])
                                ->default(function (?User $record) {
                                    if (!$record) return 'village';
                                    
                                    // Check if current email matches village email
                                    $primaryVillage = $record->getAccessibleVillages()->first();
                                    if ($primaryVillage) {
                                        $villageEmail = $primaryVillage->email ?: 'admin@' . $primaryVillage->slug . '.pamdes.id';
                                        if ($record->email === $villageEmail) {
                                            return 'village';
                                        }
                                    }
                                    return 'own';
                                })
                                ->inline()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, ?User $record) {
                                    if ($state === 'village') {
                                        // Try to get village email from record's villages or current user's villages
                                        $targetUser = $record ?: Auth::user();
                                        $primaryVillage = null;
                                        
                                        if ($targetUser) {
                                            $userModel = $record ?: User::find($targetUser->id);
                                            $primaryVillage = $userModel->getAccessibleVillages()->first();
                                        }
                                        
                                        // Fallback to any active village if no specific village found
                                        if (!$primaryVillage) {
                                            $primaryVillage = Village::where('is_active', true)->first();
                                        }
                                        
                                        if ($primaryVillage) {
                                            $villageEmail = $primaryVillage->email ?: 'admin@' . $primaryVillage->slug . '.pamdes.id';
                                            $set('email', $villageEmail);
                                        }
                                    } else {
                                        $set('email', '');
                                    }
                                })
                                ->columnSpan(2),

                            Forms\Components\Placeholder::make('village_email_info')
                                ->label('Informasi Email Desa')
                                ->content(function (Forms\Get $get, ?User $record) {
                                    $targetUser = $record ?: Auth::user();
                                    $primaryVillage = null;
                                    
                                    if ($targetUser) {
                                        $userModel = $record ?: User::find($targetUser->id);
                                        $primaryVillage = $userModel->getAccessibleVillages()->first();
                                    }
                                    
                                    if (!$primaryVillage) {
                                        $primaryVillage = Village::where('is_active', true)->first();
                                    }
                                    
                                    if ($primaryVillage) {
                                        $villageEmail = $primaryVillage->email ?: 'admin@' . $primaryVillage->slug . '.pamdes.id';
                                        return "📧 **{$villageEmail}**\n🏘️ PAMDes {$primaryVillage->name}\n✅ Email ini akan digunakan untuk semua komunikasi resmi";
                                    }
                                    
                                    return '⚠️ Email desa tidak tersedia';
                                })
                                ->visible(fn(Forms\Get $get) => $get('email_choice') === 'village')
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('email')
                                ->label(fn(Forms\Get $get) => $get('email_choice') === 'village' ? 'Email (Otomatis dari Desa)' : 'Email Pribadi')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255)
                                ->disabled(fn(Forms\Get $get) => $get('email_choice') === 'village')
                                ->dehydrated()
                                ->placeholder(function (Forms\Get $get) {
                                    if ($get('email_choice') === 'village') {
                                        return 'Email desa akan digunakan secara otomatis';
                                    }
                                    return 'Masukkan alamat email pribadi Anda';
                                })
                                ->helperText(function (Forms\Get $get) {
                                    if ($get('email_choice') === 'village') {
                                        return 'Email ini diambil dari pengaturan desa dan akan digunakan untuk semua komunikasi';
                                    }
                                    return 'Pastikan email pribadi ini aktif dan dapat menerima notifikasi';
                                })
                                ->columnSpan(2),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                        
                        ])
                        ->collapsible()
                        ->columnSpanFull(),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required(fn(string $context): bool => $context === 'create')
                            ->dehydrated(fn(?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn(string $state): string => Hash::make($state)),

                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options(function () use ($user) {
                                if ($user && $user->isSuperAdmin()) {
                                    // Super admin can create all roles
                                    return [
                                        'super_admin' => 'Super Administrator',
                                        'village_admin' => 'Village Administrator',
                                        'collector' => 'Penagih',
                                        'operator' => 'Operator',
                                    ];
                                } elseif ($user && $user->role === 'village_admin') {
                                    // Village admin can only create operators and collectors
                                    return [
                                        'collector' => 'Penagih',
                                        'operator' => 'Operator',
                                    ];
                                }

                                return [];
                            })
                            ->searchable()
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('contact_info')
                            ->label('Informasi Kontak')
                            ->maxLength(255),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Forms\Components\Section::make('Penugasan Desa')
                    ->schema([
                        Forms\Components\CheckboxList::make('villages')
                            ->label('Desa yang Dapat Diakses')
                            ->relationship('villages', 'name')
                            ->options(function () use ($user) {
                                if ($user && $user->isSuperAdmin()) {
                                    // Super admin can assign to all villages
                                    return Village::active()->pluck('name', 'id');
                                } elseif ($user && $user->role === 'village_admin') {
                                    // Village admin can only assign to their own villages
                                    return $user->getAccessibleVillages()->pluck('name', 'id');
                                }

                                return [];
                            })
                            ->searchable()
                            ->columns(2)
                            ->hidden(fn(Forms\Get $get) => $get('role') === 'super_admin')
                            ->default(function () use ($user) {
                                // For village admin, default to their villages
                                if ($user && $user->role === 'village_admin') {
                                    return $user->getAccessibleVillages()->pluck('id')->toArray();
                                }
                                return [];
                            }),

                        Forms\Components\Select::make('primary_village')
                            ->label('Desa Utama')
                            ->options(function () use ($user) {
                                if ($user && $user->isSuperAdmin()) {
                                    return Village::active()->pluck('name', 'id');
                                } elseif ($user && $user->role === 'village_admin') {
                                    return $user->getAccessibleVillages()->pluck('name', 'id');
                                }

                                return [];
                            })
                            ->searchable()
                            ->hidden(fn(Forms\Get $get) => $get('role') === 'super_admin')
                            ->helperText('Desa utama yang akan menjadi default saat login')
                            ->default(function () use ($user) {
                                // For village admin, default to their primary village
                                if ($user && $user->role === 'village_admin') {
                                    return $user->getPrimaryVillageId();
                                }
                                return null;
                            }),
                    ])
                    ->visible(fn(Forms\Get $get) => in_array($get('role'), ['village_admin', 'operator', 'collector'])),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $user = User::find($user->id);
        $isSuperAdmin = $user?->isSuperAdmin();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->colors([
                        'danger' => 'super_admin',
                        'primary' => 'village_admin',
                        'warning' => 'collector',
                        'info' => 'operator',
                    ])
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'super_admin' => 'Super Admin',
                        'village_admin' => 'Village Admin',
                        'collector' => 'Penagih',
                        'operator' => 'Operator',
                        default => $state
                    }),

                Tables\Columns\TextColumn::make('villages_list')
                    ->label('Desa')
                    ->getStateUsing(function (User $record) {
                        if ($record->isSuperAdmin()) {
                            return 'Semua Desa';
                        }
                        return $record->villages->pluck('name')->join(', ') ?: 'Tidak ada';
                    })
                    ->limit(50),

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
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options(function () use ($user) {
                        if ($user && $user->isSuperAdmin()) {
                            return [
                                'super_admin' => 'Super Admin',
                                'village_admin' => 'Village Admin',
                                'collector' => 'Penagih',
                                'operator' => 'Operator',
                            ];
                        } elseif ($user && $user->role === 'village_admin') {
                            return [
                                'village_admin' => 'Village Admin',
                                'collector' => 'Penagih',
                                'operator' => 'Operator',
                            ];
                        }

                        return [];
                    })
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->before(function (User $record) {
                            if ($record->isSuperAdmin() && User::superAdmins()->count() <= 1) {
                                throw new \Exception('Cannot delete the last super administrator.');
                            }
                        }),
                ]),
            ])
            ->headerActions([
                ...static::getExportHeaderActions(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ...static::getExportBulkActions(),
                ]),
            ])->defaultSort(
                'created_at',
                'desc'
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
