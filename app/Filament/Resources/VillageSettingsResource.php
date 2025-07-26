<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VillageSettingsResource\Pages;
use App\Models\User;
use App\Models\Village;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class VillageSettingsResource extends Resource
{
    protected static ?string $model = Village::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Pengaturan Desa';
    protected static ?string $modelLabel = 'Pengaturan Desa';
    protected static ?string $pluralModelLabel = 'Pengaturan Desa';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationGroup = 'Sistem';

    // Role-based navigation visibility
    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Only village admins can see village settings
        return $user && $user->isVillageAdmin();
    }

    // Role-based access control
    public static function canCreate(): bool
    {
        return false; // Villages are not created through this resource
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Village admin can only edit their own village settings
        return $user && $user->hasAccessToVillage($record->id);
    }

    public static function canDelete(Model $record): bool
    {
        return false; // Villages cannot be deleted through this resource
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Village admins can view their village settings
        return $user && $user->isVillageAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $user = User::find($user->id);

        $query = parent::getEloquentQuery();

        // Village admins can only see their accessible villages
        if ($user && $user->isVillageAdmin()) {
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            if ($accessibleVillages->isNotEmpty()) {
                return $query->whereIn('id', $accessibleVillages);
            } else {
                return $query->whereRaw('1 = 0');
            }
        }

        // Return empty query for non-village admins
        return $query->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Desa')
                    ->schema([
                        Forms\Components\Placeholder::make('name')
                            ->label('Nama Desa')
                            ->content(fn(?Village $record) => $record?->name ?? '-'),
                            
                        Forms\Components\Placeholder::make('description')
                            ->label('Deskripsi')
                            ->content(fn(?Village $record) => $record?->description ?? '-'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pengaturan Biaya')
                    ->description('Atur biaya admin dan pemeliharaan untuk tagihan air')
                    ->schema([
                        Forms\Components\TextInput::make('pamdes_settings.default_admin_fee')
                            ->label('Biaya Admin')
                            ->helperText('Biaya administrasi yang dikenakan untuk setiap tagihan')
                            ->numeric()
                            ->default(5000)
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0)
                            ->maxValue(50000),

                        Forms\Components\TextInput::make('pamdes_settings.default_maintenance_fee')
                            ->label('Biaya Pemeliharaan')
                            ->helperText('Biaya pemeliharaan yang dikenakan untuk setiap tagihan')
                            ->numeric()
                            ->default(2000)
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0)
                            ->maxValue(50000),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pengaturan Sistem')
                    ->schema([
                        Forms\Components\Toggle::make('pamdes_settings.auto_generate_bills')
                            ->label('Auto Generate Tagihan')
                            ->helperText('Otomatis buat tagihan setiap bulan berdasarkan data pembacaan meter')
                            ->default(true),

                        Forms\Components\Toggle::make('pamdes_settings.late_fee_enabled')
                            ->label('Aktifkan Denda Keterlambatan')
                            ->helperText('Kenakan denda untuk tagihan yang terlambat dibayar')
                            ->default(false),

                        Forms\Components\Toggle::make('pamdes_settings.allow_partial_payments')
                            ->label('Izinkan Pembayaran Sebagian')
                            ->helperText('Membolehkan pelanggan membayar tagihan secara bertahap')
                            ->default(true),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Desa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pamdes_settings.default_admin_fee')
                    ->label('Biaya Admin')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pamdes_settings.default_maintenance_fee')
                    ->label('Biaya Pemeliharaan')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\IconColumn::make('pamdes_settings.auto_generate_bills')
                    ->label('Auto Generate')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diubah')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit Pengaturan'),
            ])
            ->bulkActions([
                // No bulk actions for village settings
            ])
            ->emptyStateHeading('Tidak ada pengaturan desa')
            ->emptyStateDescription('Hubungi administrator untuk mengatur akses desa Anda.');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVillageSettings::route('/'),
            'edit' => Pages\EditVillageSettings::route('/{record}/edit'),
        ];
    }
}
