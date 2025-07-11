<?php
// app/Filament/Resources/WaterTariffResource.php - Updated with village display

namespace App\Filament\Resources;

use App\Filament\Resources\WaterTariffResource\Pages;
use App\Models\WaterTariff;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class WaterTariffResource extends Resource
{
    protected static ?string $model = WaterTariff::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Tarif Air';
    protected static ?string $modelLabel = 'Tarif Air';
    protected static ?string $pluralModelLabel = 'Tarif Air';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationGroup = 'Pengaturan';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('village');

        $user = User::find(Auth::user()->id);
        $currentVillage = $user?->getCurrentVillageContext();

        if ($user?->isSuperAdmin() && $currentVillage) {
            // Show global tariffs and village-specific tariffs
            $query->where(function ($q) use ($currentVillage) {
                $q->where('village_id', $currentVillage)
                    ->orWhereNull('village_id');
            });
        } elseif ($user?->isVillageAdmin()) {
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            $query->where(function ($q) use ($accessibleVillages) {
                $q->whereIn('village_id', $accessibleVillages)
                    ->orWhereNull('village_id'); // Include global tariffs
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $user = Auth::user();
        $user = User::find($user->id);
        $currentVillageId = $user?->getCurrentVillageContext();

        return $form
            ->schema([
                Forms\Components\Section::make('Pengaturan Tarif')
                    ->schema([
                        Forms\Components\Select::make('village_id')
                            ->label('Desa')
                            ->relationship('village', 'name')
                            ->default($currentVillageId)
                            ->placeholder('Global (Semua Desa)')
                            ->helperText('Kosongkan untuk tarif global yang berlaku untuk semua desa')
                            ->visible(fn() => $user?->isSuperAdmin()),

                        Forms\Components\Placeholder::make('village_display')
                            ->label('Desa')
                            ->content(function (?WaterTariff $record) use ($currentVillageId) {
                                if ($record) {
                                    return $record->village ? $record->village->name : 'Global (Semua Desa)';
                                }
                                if ($currentVillageId) {
                                    $village = \App\Models\Village::find($currentVillageId);
                                    return $village?->name ?? 'Unknown Village';
                                }
                                return 'Village Context';
                            })
                            ->visible(fn() => !$user?->isSuperAdmin()),

                        Forms\Components\Hidden::make('village_id')
                            ->default($currentVillageId)
                            ->visible(fn() => !$user?->isSuperAdmin()),

                        Forms\Components\TextInput::make('usage_min')
                            ->label('Pemakaian Minimum (m³)')
                            ->required()
                            ->numeric()
                            ->minValue(0),

                        Forms\Components\TextInput::make('usage_max')
                            ->label('Pemakaian Maksimum (m³)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->gte('usage_min'),

                        Forms\Components\TextInput::make('price_per_m3')
                            ->label('Harga per m³')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columns(2),
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
                    ->color('primary')
                    ->formatStateUsing(fn($state) => $state ?: 'Global'),

                Tables\Columns\TextColumn::make('usage_range')
                    ->label('Rentang Pemakaian')
                    ->sortable(['usage_min', 'usage_max']),

                Tables\Columns\TextColumn::make('price_per_m3')
                    ->label('Harga per m³')
                    ->money('IDR')
                    ->sortable(),

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

                Tables\Filters\Filter::make('global')
                    ->label('Tarif Global')
                    ->query(fn($query) => $query->whereNull('village_id'))
                    ->visible($isSuperAdmin),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('usage_min');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaterTariffs::route('/'),
            'create' => Pages\CreateWaterTariff::route('/create'),
            'view' => Pages\ViewWaterTariff::route('/{record}'),
            'edit' => Pages\EditWaterTariff::route('/{record}/edit'),
        ];
    }
}
