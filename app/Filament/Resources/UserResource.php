<?php
// app/Filament/Resources/UserResource.php

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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    use ExportableResource; // Add this trait

    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Kelola Admin';
    protected static ?string $modelLabel = 'Admin';
    protected static ?string $pluralModelLabel = 'Admin';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationGroup = 'Super Admin';

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);
        return $user && $user->isSuperAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $user = User::find($user->id);
        // Super admin can see all users, village admin cannot access this resource
        if ($user && $user->isSuperAdmin()) {
            return parent::getEloquentQuery();
        }

        // Return empty query for non-super admins (shouldn't reach here due to shouldRegisterNavigation)
        return parent::getEloquentQuery()->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pengguna')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required(fn(string $context): bool => $context === 'create')
                            ->dehydrated(fn(?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn(string $state): string => Hash::make($state)),

                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options([
                                'super_admin' => 'Super Administrator',
                                'village_admin' => 'Village Administrator',
                                'collector' => 'Penagih',
                                'operator' => 'Operator',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('contact_info')
                            ->label('Informasi Kontak')
                            ->maxLength(255),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Penugasan Desa')
                    ->schema([
                        Forms\Components\CheckboxList::make('villages')
                            ->label('Desa yang Dapat Diakses')
                            ->relationship('villages', 'name')
                            ->options(Village::active()->pluck('name', 'id'))
                            ->columns(2)
                            ->hidden(fn(Forms\Get $get) => $get('role') === 'super_admin'),

                        Forms\Components\Select::make('primary_village')
                            ->label('Desa Utama')
                            ->options(Village::active()->pluck('name', 'id'))
                            ->hidden(fn(Forms\Get $get) => $get('role') === 'super_admin')
                            ->helperText('Desa utama yang akan menjadi default saat login'),
                    ])
                    ->visible(fn(Forms\Get $get) => $get('role') === 'village_admin'),
            ]);
    }

    public static function table(Table $table): Table
    {
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
                    ->options([
                        'super_admin' => 'Super Admin',
                        'village_admin' => 'Village Admin',
                    ]),

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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
