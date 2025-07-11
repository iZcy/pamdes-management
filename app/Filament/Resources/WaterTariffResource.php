<?php
// app/Filament/Resources/WaterTariffResource.php - Updated with smart range management

namespace App\Filament\Resources;

use App\Filament\Resources\WaterTariffResource\Pages;
use App\Models\WaterTariff;
use App\Models\User;
use App\Services\TariffRangeService;
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
            $query->where('village_id', $currentVillage);
        } elseif ($user?->isVillageAdmin()) {
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            $query->whereIn('village_id', $accessibleVillages);
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
                            ->required()
                            ->disabled(fn(?WaterTariff $record) => $record !== null) // Can't change village on edit
                            ->visible(fn() => $user?->isSuperAdmin()),

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
                            ->default($currentVillageId)
                            ->visible(fn() => !$user?->isSuperAdmin()),

                        // For creating new tariff - only need minimum value
                        Forms\Components\TextInput::make('usage_min')
                            ->label('Pemakaian Minimum (m³)')
                            ->required(fn(string $context) => $context === 'create')
                            ->numeric()
                            ->minValue(0)
                            ->disabled(
                                fn(string $context, ?WaterTariff $record) =>
                                $context === 'edit' && $record && !app(TariffRangeService::class)->getEditableFields($record)['can_edit_min']
                            )
                            ->helperText(
                                fn(string $context, ?WaterTariff $record) =>
                                $context === 'create'
                                    ? 'Sistem akan otomatis mengatur rentang yang tidak bertumpuk'
                                    : ($record && app(TariffRangeService::class)->getEditableFields($record)['can_edit_min']
                                        ? 'Mengubah nilai ini akan menyesuaikan rentang sebelumnya'
                                        : 'Hanya rentang terakhir yang dapat mengedit minimum')
                            ),

                        // For editing - show current range and allow editing max (except for last range)
                        Forms\Components\TextInput::make('usage_max')
                            ->label('Pemakaian Maksimum (m³)')
                            ->numeric()
                            ->minValue(0)
                            ->disabled(
                                fn(string $context, ?WaterTariff $record) =>
                                $context === 'create' ||
                                    ($record && !app(TariffRangeService::class)->getEditableFields($record)['can_edit_max'])
                            )
                            ->visible(fn(string $context) => $context === 'edit')
                            ->helperText(
                                fn(?WaterTariff $record) =>
                                $record && app(TariffRangeService::class)->getEditableFields($record)['is_last_range']
                                    ? 'Rentang terakhir (unlimited) - tidak dapat diubah'
                                    : 'Mengubah nilai ini akan menyesuaikan rentang berikutnya'
                            ),

                        Forms\Components\Placeholder::make('current_range')
                            ->label('Rentang Saat Ini')
                            ->content(fn(?WaterTariff $record) => $record ? $record->usage_range : 'Akan dibuat otomatis')
                            ->visible(fn(string $context, ?WaterTariff $record) => $context === 'edit' && $record),

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

                // Show existing tariffs for context
                Forms\Components\Section::make('Tarif Saat Ini')
                    ->schema([
                        Forms\Components\Placeholder::make('existing_tariffs')
                            ->label('')
                            ->content(function () use ($currentVillageId) {
                                if (!$currentVillageId) return 'Pilih desa terlebih dahulu';

                                $tariffs = app(TariffRangeService::class)->getVillageTariffs($currentVillageId);

                                if (empty($tariffs)) {
                                    return 'Belum ada tarif untuk desa ini';
                                }

                                $content = '<div class="space-y-2">';
                                foreach ($tariffs as $tariff) {
                                    $editableInfo = [];
                                    if ($tariff['editable_fields']['can_edit_min']) $editableInfo[] = 'min';
                                    if ($tariff['editable_fields']['can_edit_max']) $editableInfo[] = 'max';
                                    $editableText = !empty($editableInfo) ? ' (dapat edit: ' . implode(', ', $editableInfo) . ')' : '';

                                    $content .= '<div class="flex justify-between items-center p-2 bg-gray-50 rounded">';
                                    $content .= '<span class="font-medium">' . $tariff['range_display'] . '</span>';
                                    $content .= '<span class="text-green-600">Rp ' . number_format($tariff['price_per_m3']) . $editableText . '</span>';
                                    $content .= '</div>';
                                }
                                $content .= '</div>';

                                return new \Illuminate\Support\HtmlString($content);
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(string $context) => $context === 'create')
                    ->collapsible(),
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $service = app(TariffRangeService::class);
                            foreach ($records as $record) {
                                $service->deleteTariffRange($record);
                            }
                        }),
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
