<?php
// app/Filament/Resources/BillingPeriodResource.php - Updated with village display

namespace App\Filament\Resources;

use App\Filament\Resources\BillingPeriodResource\Pages;
use App\Models\BillingPeriod;
use App\Models\User;
use App\Traits\ExportableResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BillingPeriodResource extends Resource
{
    use ExportableResource; // Add this trait

    protected static ?string $model = BillingPeriod::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Periode Tagihan';
    protected static ?string $modelLabel = 'Periode Tagihan';
    protected static ?string $pluralModelLabel = 'Periode Tagihan';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = 'Manajemen Data';

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
                Forms\Components\Section::make('Periode Tagihan')
                    ->schema([
                        Forms\Components\Select::make('village_id')
                            ->label('Desa')
                            ->relationship('village', 'name')
                            ->default($currentVillageId)
                            ->disabled() // Read-only to prevent moving periods between villages
                            ->dehydrated()
                            ->required()
                            ->visible(fn() => $user?->isSuperAdmin()),

                        Forms\Components\Placeholder::make('village_display')
                            ->label('Desa')
                            ->content(function (?BillingPeriod $record) use ($currentVillageId) {
                                if ($record && $record->village) {
                                    return $record->village->name;
                                }
                                if ($currentVillageId) {
                                    $village = \App\Models\Village::find($currentVillageId);
                                    return $village?->name ?? 'Unknown Village';
                                }
                                return 'No Village Selected';
                            })
                            ->columnSpanFull(fn() => !$user?->isSuperAdmin())
                            ->visible(fn() => !$user?->isSuperAdmin()),

                        Forms\Components\Hidden::make('village_id')
                            ->default($currentVillageId)
                            ->visible(fn() => !$user?->isSuperAdmin()),

                        Forms\Components\Select::make('month')
                            ->label('Bulan')
                            ->options([
                                1 => 'Januari',
                                2 => 'Februari',
                                3 => 'Maret',
                                4 => 'April',
                                5 => 'Mei',
                                6 => 'Juni',
                                7 => 'Juli',
                                8 => 'Agustus',
                                9 => 'September',
                                10 => 'Oktober',
                                11 => 'November',
                                12 => 'Desember'
                            ])
                            ->searchable()
                            ->required()
                            ->default(now()->month),

                        Forms\Components\TextInput::make('year')
                            ->label('Tahun')
                            ->required()
                            ->numeric()
                            ->minValue(2020)
                            ->maxValue(2030)
                            ->default(now()->year),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'inactive' => 'Tidak Aktif',
                                'active' => 'Aktif',
                                'completed' => 'Selesai',
                            ])
                            ->searchable()
                            ->default('inactive')
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Jadwal')
                    ->schema([
                        Forms\Components\DatePicker::make('reading_start_date')
                            ->label('Tanggal Mulai Baca Meter'),

                        Forms\Components\DatePicker::make('reading_end_date')
                            ->label('Tanggal Selesai Baca Meter'),

                        Forms\Components\DatePicker::make('billing_due_date')
                            ->label('Tanggal Jatuh Tempo'),
                    ])
                    ->columns(3),
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

                Tables\Columns\TextColumn::make('period_name')
                    ->label('Periode')
                    ->sortable(['year', 'month']),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'gray' => 'inactive',
                        'success' => 'active',
                        'primary' => 'completed',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'inactive' => 'Tidak Aktif',
                        'active' => 'Aktif',
                        'completed' => 'Selesai',
                    }),

                Tables\Columns\TextColumn::make('total_customers')
                    ->label('Jumlah Pelanggan')
                    ->numeric()
                    ->getStateUsing(function (BillingPeriod $record): int {
                        return $record->total_customers;
                    }),

                Tables\Columns\TextColumn::make('total_billed')
                    ->label('Total Tagihan')
                    ->money('IDR')
                    ->getStateUsing(function (BillingPeriod $record): float {
                        return $record->total_billed;
                    }),

                Tables\Columns\TextColumn::make('collection_rate')
                    ->label('Tingkat Penagihan')
                    ->getStateUsing(function (BillingPeriod $record): string {
                        return number_format($record->collection_rate, 1) . '%';
                    })
                    ->badge()
                    ->color(fn(BillingPeriod $record): string => match (true) {
                        $record->collection_rate >= 90 => 'success',
                        $record->collection_rate >= 75 => 'primary',
                        $record->collection_rate >= 50 => 'warning',
                        default => 'danger'
                    }),

                Tables\Columns\TextColumn::make('billing_due_date')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('village_id')
                    ->label('Desa')
                    ->relationship('village', 'name')
                    ->visible($isSuperAdmin),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'inactive' => 'Tidak Aktif',
                        'active' => 'Aktif',
                        'completed' => 'Selesai',
                    ])
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('generate_bills')
                        ->label('Generate Tagihan')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->visible(fn(BillingPeriod $record): bool => $record->status === 'active')
                        ->action(function (BillingPeriod $record) {
                            $recorded = 0;
                            $record->waterUsages->each(function ($usage) use (&$recorded) {
                                $village = \App\Models\Village::find($usage->customer->village_id);
                                if (!$usage->bill()->exists()) {
                                    $usage->generateBill([
                                        'admin_fee' => $village?->getDefaultAdminFee() ?? 5000,
                                        'maintenance_fee' => $village?->getDefaultMaintenanceFee() ?? 2000,
                                    ]);

                                    $recorded++;
                                }
                            });

                            if ($recorded === 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Tidak ada tagihan baru')
                                    ->body('Tidak ada tagihan yang perlu dibuat untuk periode ini.')
                                    ->warning()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Tagihan berhasil dibuat')
                                    ->body("Berhasil membuat {$recorded} tagihan untuk periode {$record->period_name}.")
                                    ->success()
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
                    Tables\Actions\DeleteBulkAction::make(),
                    ...static::getExportBulkActions(),
                ]),
            ])
            ->defaultSort('year', 'desc')
            ->defaultSort('month', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillingPeriods::route('/'),
            'create' => Pages\CreateBillingPeriod::route('/create'),
            'edit' => Pages\EditBillingPeriod::route('/{record}/edit'),
        ];
    }
}
