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

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),

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
                                ->afterStateUpdated(function (Forms\Set $set, $state, Forms\Get $get) {
                                    // Live preview of what will happen
                                    if ($state !== null && $state >= 0) {
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

                            Forms\Components\TextInput::make('usage_max')
                                ->label('Pemakaian Maksimum (m³)')
                                ->numeric()
                                ->minValue(0)
                                ->disabled(function (string $context, ?WaterTariff $record) {
                                    if ($context === 'create') return true;
                                    if (!$record) return true;

                                    $service = app(TariffRangeService::class);
                                    $editableFields = $service->getEditableFields($record);
                                    return !$editableFields['can_edit_max'];
                                })
                                ->visible(fn(string $context) => $context === 'edit')
                                ->helperText(function (?WaterTariff $record) {
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
                                ->content(fn(?WaterTariff $record) => $record ? $record->usage_range : 'Akan dibuat otomatis')
                                ->visible(fn(string $context, ?WaterTariff $record) => $context === 'edit' && $record),

                            Forms\Components\TextInput::make('price_per_m3')
                                ->label('Harga per m³')
                                ->required()
                                ->numeric()
                                ->prefix('Rp')
                                ->minValue(0)
                                ->helperText('Harga dapat selalu diubah'),
                        ])->columnSpan(2),
                    ])->columns(2),

                // Show existing tariffs for context
                Forms\Components\Section::make('Tarif Saat Ini')
                    ->schema([
                        Forms\Components\Placeholder::make('existing_tariffs')
                            ->label('')
                            ->content(function (?WaterTariff $record, Forms\Get $get) {
                                $villageId = $record?->village_id ?? $get('village_id') ?? config('pamdes.current_village_id');

                                if (!$villageId) return 'Pilih desa terlebih dahulu';

                                try {
                                    $service = app(TariffRangeService::class);
                                    $tariffs = $service->getVillageTariffs($villageId);

                                    if (empty($tariffs)) {
                                        return '<div class="text-transparent italic">Belum ada tarif untuk desa ini</div>';
                                    }

                                    $content = '<div class="space-y-2">';
                                    foreach ($tariffs as $tariff) {
                                        $editableInfo = [];
                                        if ($tariff['editable_fields']['can_edit_min']) $editableInfo[] = 'min';
                                        if ($tariff['editable_fields']['can_edit_max']) $editableInfo[] = 'max';
                                        $editableText = !empty($editableInfo) ? ' <span class="text-xs text-blue-600">(dapat edit: ' . implode(', ', $editableInfo) . ')</span>' : '';

                                        $content .= '<div class="flex justify-between items-center p-3 bg-transparent rounded-lg border">';
                                        $content .= '<span class="font-medium text-transparent">' . $tariff['range_display'] . '</span>';
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
                    ->visible(fn(string $context) => $context === 'create')
                    ->collapsible(),

                // Show example calculations
                Forms\Components\Section::make('Contoh Perhitungan')
                    ->schema([
                        Forms\Components\Placeholder::make('calculations')
                            ->label('')
                            ->content(function (?WaterTariff $record, Forms\Get $get) {
                                $villageId = $record?->village_id ?? $get('village_id') ?? config('pamdes.current_village_id');

                                if (!$villageId) return 'Pilih desa untuk melihat contoh perhitungan';

                                try {
                                    $content = '<div class="space-y-3">';
                                    $content .= '<div class="text-sm text-transparent mb-3">Contoh perhitungan biaya air:</div>';

                                    $usageExamples = [10, 15, 25, 35, 50];

                                    foreach ($usageExamples as $usage) {
                                        try {
                                            $calculation = \App\Models\WaterTariff::calculateBill($usage, $villageId);

                                            $breakdown = [];
                                            foreach ($calculation['breakdown'] as $tier) {
                                                $breakdown[] = "{$tier['usage']} m³ × Rp" . number_format($tier['rate']);
                                            }

                                            $content .= '<div class="flex justify-between items-center p-2 bg-transparent rounded">';
                                            $content .= '<span class="font-medium">' . $usage . ' m³:</span>';
                                            $content .= '<span class="text-sm text-transparent">' . implode(' + ', $breakdown) . '</span>';
                                            $content .= '<span class="font-semibold text-green-600">Rp ' . number_format($calculation['total_charge']) . '</span>';
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
