<?php
// app/Filament/Resources/BillResource.php - Updated with collector restrictions

namespace App\Filament\Resources;

use App\Filament\Resources\BillResource\Pages;
use App\Models\Bill;
use App\Models\User;
use App\Models\WaterUsage;
use App\Traits\ExportableResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BillResource extends Resource
{
    use ExportableResource;

    protected static ?string $model = Bill::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Tagihan';
    protected static ?string $modelLabel = 'Tagihan';
    protected static ?string $pluralModelLabel = 'Tagihan';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationGroup = 'Tagihan & Pembayaran';

    // Role-based access control
    public static function canCreate(): bool
    {
        $user = User::find(Auth::user()->id);

        // Only super_admin and village_admin can create bills
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canEdit(Model $record): bool
    {
        $user = User::find(Auth::user()->id);

        // Only super_admin and village_admin can edit bills
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canDelete(Model $record): bool
    {
        $user = User::find(Auth::user()->id);

        // Only super_admin and village_admin can delete bills
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

        // All roles can view bills, but with different scopes
        return $user && in_array($user->role, ['super_admin', 'village_admin', 'collector', 'operator']);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['waterUsage.customer.village']);
        $user = User::find(Auth::user()->id);
        $currentVillage = $user?->getCurrentVillageContext();

        if ($user?->isSuperAdmin() && $currentVillage) {
            // Super admin sees bills for current village context
            $query->whereHas('waterUsage.customer', function ($q) use ($currentVillage) {
                $q->where('village_id', $currentVillage);
            });
        } elseif ($user?->isVillageAdmin()) {
            // Village admin sees bills for their accessible villages
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            $query->whereHas('waterUsage.customer', function ($q) use ($accessibleVillages) {
                $q->whereIn('village_id', $accessibleVillages);
            });
        } elseif ($user?->isCollector()) {
            // Collector sees only unpaid/overdue bills in their villages
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            $query->whereHas('waterUsage.customer', function ($q) use ($accessibleVillages) {
                $q->whereIn('village_id', $accessibleVillages);
            })->whereIn('status', ['unpaid', 'overdue', 'pending']);
        } elseif ($user?->role === 'operator') {
            // Operator has read-only access to bills in their villages
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            $query->whereHas('waterUsage.customer', function ($q) use ($accessibleVillages) {
                $q->whereIn('village_id', $accessibleVillages);
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $user = User::find(Auth::user()->id);

        // Collectors and operators cannot access the form
        if ($user && in_array($user->role, ['collector', 'operator'])) {
            return $form->schema([
                Forms\Components\Placeholder::make('access_denied')
                    ->label('Akses Ditolak')
                    ->content('Anda tidak memiliki izin untuk mengubah data tagihan.')
                    ->columnSpanFull(),
            ]);
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Tagihan')
                    ->schema([
                        Forms\Components\Placeholder::make('village_info')
                            ->label('Desa')
                            ->content(function (?Bill $record) {
                                if ($record && $record->waterUsage?->customer?->village) {
                                    return $record->waterUsage->customer->village->name;
                                }
                                $user = User::find(Auth::user()->id);
                                $currentVillage = $user?->getCurrentVillageContext();
                                if ($currentVillage) {
                                    $village = \App\Models\Village::find($currentVillage);
                                    return $village?->name ?? 'Unknown Village';
                                }
                                return 'No Village Context';
                            })
                            ->columnSpanFull(),

                        Forms\Components\Select::make('usage_id')
                            ->label('Pembacaan Meter')
                            ->options(function () {
                                $user = User::find(Auth::user()->id);
                                $currentVillage = $user?->getCurrentVillageContext();

                                if (!$currentVillage) {
                                    return [];
                                }

                                return WaterUsage::whereHas('customer', function ($q) use ($currentVillage) {
                                    $q->where('village_id', $currentVillage);
                                })->with(['customer', 'billingPeriod'])
                                    ->get()
                                    ->mapWithKeys(function ($usage) {
                                        return [
                                            $usage->usage_id => "{$usage->customer->customer_code} - {$usage->customer->name} ({$usage->billingPeriod->period_name})"
                                        ];
                                    });
                            })
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('water_charge')
                            ->label('Biaya Air')
                            ->required()
                            ->numeric()
                            ->prefix('Rp'),

                        Forms\Components\TextInput::make('admin_fee')
                            ->label('Biaya Admin')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->default(function () {
                                $user = User::find(Auth::user()->id);
                                $village = \App\Models\Village::find($user?->getCurrentVillageContext());
                                return $village?->getDefaultAdminFee() ?? 5000;
                            }),

                        Forms\Components\TextInput::make('maintenance_fee')
                            ->label('Biaya Pemeliharaan')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->default(function () {
                                $user = User::find(Auth::user()->id);
                                $village = \App\Models\Village::find($user?->getCurrentVillageContext());
                                return $village?->getDefaultMaintenanceFee() ?? 2000;
                            }),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Tagihan')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                $water = $get('water_charge') ?? 0;
                                $admin = $get('admin_fee') ?? 0;
                                $maintenance = $get('maintenance_fee') ?? 0;
                                $set('total_amount', $water + $admin + $maintenance);
                            }),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'unpaid' => 'Belum Bayar',
                                'paid' => 'Sudah Bayar',
                                'overdue' => 'Terlambat',
                            ])
                            ->searchable()
                            ->default('unpaid')
                            ->required(),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('Tanggal Jatuh Tempo')
                            ->required(),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->visible(fn(Forms\Get $get) => $get('status') === 'paid'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
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
                Tables\Columns\TextColumn::make('waterUsage.customer.village.name')
                    ->label('Desa')
                    ->searchable()
                    ->sortable()
                    ->visible($isSuperAdmin)
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('waterUsage.customer.customer_code')
                    ->label('Kode Pelanggan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('waterUsage.customer.name')
                    ->label('Nama Pelanggan')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('waterUsage.billingPeriod.period_name')
                    ->label('Periode')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->join('water_usages', 'bills.usage_id', '=', 'water_usages.usage_id')
                            ->join('billing_periods', 'water_usages.period_id', '=', 'billing_periods.period_id')
                            ->orderBy('billing_periods.year', $direction)
                            ->orderBy('billing_periods.month', $direction)
                            ->select('bills.*');
                    }),

                Tables\Columns\TextColumn::make('waterUsage.total_usage_m3')
                    ->label('Pemakaian')
                    ->suffix(' m³')
                    ->numeric(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Tagihan')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => 'unpaid',
                        'success' => 'paid',
                        'danger' => 'overdue',
                        'primary' => 'pending',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'unpaid' => 'Belum Bayar',
                        'paid' => 'Sudah Bayar',
                        'overdue' => 'Terlambat',
                        'pending' => 'Dalam Proses',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('village')
                    ->label('Desa')
                    ->relationship('waterUsage.customer.village', 'name')
                    ->visible($isSuperAdmin),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'unpaid' => 'Belum Bayar',
                        'paid' => 'Sudah Bayar',
                        'overdue' => 'Terlambat',
                    ])
                    ->searchable(),

                Tables\Filters\Filter::make('overdue')
                    ->label('Tagihan Terlambat')
                    ->query(fn($query) => $query->overdue()),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    // Edit only for super_admin and village_admin
                    Tables\Actions\EditAction::make()
                        ->visible(fn() => !$isCollector && !$isOperator),

                    // Print Receipt - available for all roles
                    Tables\Actions\Action::make('print_receipt')
                        ->label('Cetak Kwitansi')
                        ->icon('heroicon-o-printer')
                        ->color('primary')
                        ->url(fn(Bill $record): string => route('bill.receipt', $record))
                        ->openUrlInNewTab()
                        ->tooltip('Cetak/Lihat kwitansi tagihan'),

                    // Mark as Paid - only for collectors and above
                    Tables\Actions\Action::make('mark_paid')
                        ->label('Tandai Lunas')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(
                            fn(Bill $record): bool =>
                            $record->canBePaid() && ($isCollector || !$isOperator)
                        )
                        ->form([
                            Forms\Components\DatePicker::make('payment_date')
                                ->label('Tanggal Pembayaran')
                                ->default(now())
                                ->required(),
                            Forms\Components\TextInput::make('amount_paid')
                                ->label('Jumlah Dibayar')
                                ->numeric()
                                ->prefix('Rp')
                                ->required(),
                            Forms\Components\Select::make('payment_method')
                                ->label('Metode Pembayaran')
                                ->options([
                                    'cash' => 'Tunai',
                                    'transfer' => 'Transfer',
                                    'qris' => 'QRIS',
                                    'other' => 'Lainnya',
                                ])
                                ->searchable()
                                ->default('cash')
                                ->required(),
                        ])
                        ->action(function (Bill $record, array $data) {
                            $record->markAsPaid($data);
                        }),
                ])
            ])
            ->headerActions([
                // Export actions only for admin roles
                ...($isCollector || $isOperator ? [] : static::getExportHeaderActions()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Delete only for admin roles
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => !$isCollector && !$isOperator),

                    // Bulk Print - available for all roles
                    Tables\Actions\BulkAction::make('bulk_print')
                        ->label('Cetak Kwitansi Terpilih')
                        ->icon('heroicon-o-printer')
                        ->color('primary')
                        ->action(function ($records) {
                            $urls = $records->map(fn(Bill $bill) => route('bill.receipt', $bill))->toArray();
                            return redirect()->back()->with('openUrls', $urls);
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Export actions only for admin roles
                    ...($isCollector || $isOperator ? [] : static::getExportBulkActions()),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBills::route('/'),
            'create' => Pages\CreateBill::route('/create'),
            'edit' => Pages\EditBill::route('/{record}/edit'),
        ];
    }
}
