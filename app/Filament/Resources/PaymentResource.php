<?php
// app/Filament/Resources/PaymentResource.php - Updated with role-based access

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Models\User;
use App\Models\Collector;
use App\Models\Village;
use App\Traits\ExportableResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PaymentResource extends Resource
{
    use ExportableResource;

    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Pembayaran';
    protected static ?string $modelLabel = 'Pembayaran';
    protected static ?string $pluralModelLabel = 'Pembayaran';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationGroup = 'Tagihan & Pembayaran';

    // Role-based navigation visibility
    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Super admin and village admin have full access
        if ($user?->isSuperAdmin() || $user?->role === 'village_admin') {
            return true;
        }

        // Collectors can access payments (their main function)
        if ($user?->role === 'collector') {
            return true;
        }

        // Operators cannot access payments
        return false;
    }

    // Role-based record access
    public static function canCreate(): bool
    {
        // Payments should only be made through bill pay actions, not created manually
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = User::find(Auth::user()->id);

        // Super admin and village admin can edit
        if ($user?->isSuperAdmin() || $user?->role === 'village_admin') {
            return true;
        }

        // Collectors can edit their own payments within 24 hours
        if ($user?->role === 'collector') {
            return $record->collector_id === $user->id &&
                $record->created_at->gt(now()->subDay());
        }

        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = User::find(Auth::user()->id);

        // Only super admin and village admin can delete
        return $user?->isSuperAdmin() || $user?->role === 'village_admin';
    }

    public static function canDeleteAny(): bool
    {
        $user = User::find(Auth::user()->id);
        return $user?->isSuperAdmin() || $user?->role === 'village_admin';
    }

    /**
     * Get the current village ID with proper fallback logic
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

        // For village users, try multiple approaches
        $villageId = $user->getCurrentVillageContext();

        // If still no village, try fallbacks
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['bills.customer.village', 'collector']);

        $user = User::find(Auth::user()->id);
        $currentVillage = static::getCurrentVillageId();

        if ($user?->isSuperAdmin() && $currentVillage) {
            $query->whereHas('bills.customer', function ($q) use ($currentVillage) {
                $q->where('village_id', $currentVillage);
            });
        } elseif ($user?->role === 'village_admin') {
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            if ($accessibleVillages->isNotEmpty()) {
                $query->whereHas('bills.customer', function ($q) use ($accessibleVillages) {
                    $q->whereIn('village_id', $accessibleVillages);
                });
            } else {
                // If no accessible villages, return empty result
                $query->whereRaw('1 = 0');
            }
        } elseif ($user?->role === 'collector') {
            // Collectors see all payments in their village(s) for reference
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            if ($accessibleVillages->isNotEmpty()) {
                $query->whereHas('bills.customer', function ($q) use ($accessibleVillages) {
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
        $isCollector = $user?->role === 'collector';

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pembayaran')
                    ->schema([
                        Forms\Components\Placeholder::make('village_info')
                            ->label('Desa')
                            ->content(function (?Payment $record) {
                                if ($record && $record->bills->isNotEmpty()) {
                                    $firstBill = $record->bills->first();
                                    return $firstBill?->customer?->village?->name;
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

                        Forms\Components\Select::make('bill_id')
                            ->label('Tagihan')
                            ->relationship('bill')
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                $customer = $record->waterUsage->customer;
                                $period = $record->waterUsage->billingPeriod->period_name;
                                return "{$customer->customer_code} - {$customer->name} - {$period} (Rp " . number_format($record->total_amount) . ")";
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(2)
                            ->disabled(true) // Always disabled - bills are not editable
                            ->helperText('Tagihan tidak dapat diubah setelah pembayaran dibuat'),

                        // Collector field - auto-fill for collectors, selectable for admins
                        Forms\Components\Select::make('collector_id')
                            ->label('Penagih/Kasir')
                            ->options(function () {
                                $user = Auth::user();
                                $user = User::find($user->id);
                                $currentVillage = $user?->getCurrentVillageContext();

                                if (!$currentVillage) {
                                    return [];
                                }

                                return User::whereHas('villages', function ($q) use ($currentVillage) {
                                    $q->where('villages.id', $currentVillage);
                                })
                                    ->whereIn('role', ['collector', 'operator'])
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(function ($user) {
                                        return [$user->id => $user->name . ' (' . $user->display_role . ')'];
                                    })
                                    ->toArray();
                            })
                            ->searchable()
                            ->default(function () use ($isCollector) {
                                // Auto-fill collector ID for collectors
                                return $isCollector ? Auth::id() : null;
                            })
                            ->disabled(fn (Get $get): bool => 
                                $isCollector || $get('payment_method') === 'qris'
                            ) // Collectors cannot change this, QRIS payments cannot change collector
                            ->dehydrated(),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Jumlah Dibayar')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->step(100)
                            ->reactive()
                            ->disabled(fn (Get $get): bool => $get('payment_method') === 'qris')
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => static::updateChangeGiven($set, $get)),

                        Forms\Components\TextInput::make('change_given')
                            ->label('Kembalian')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->disabled(fn (Get $get): bool => $get('payment_method') === 'qris')
                            ->readOnly()
                            ->extraAttributes(['class' => 'bg-gray-100']),

                        Forms\Components\Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options([
                                'cash' => 'Tunai',
                                'transfer' => 'Transfer Bank',
                                'qris' => 'QRIS',
                                'other' => 'Lainnya',
                            ])
                            ->searchable()
                            ->default('cash')
                            ->required()
                            ->reactive()
                            ->disabled(fn (Get $get): bool => $get('payment_method') === 'qris'),

                        Forms\Components\Select::make('status')
                            ->label('Status Pembayaran')
                            ->options([
                                'completed' => 'Selesai',
                                'pending' => 'Menunggu',
                                'expired' => 'Kedaluwarsa',
                                'failed' => 'Gagal',
                            ])
                            ->searchable()
                            ->default('completed')
                            ->required()
                            ->disabled(fn (Get $get): bool => $get('payment_method') === 'qris')
                            ->helperText('Status pembayaran QRIS dikelola otomatis oleh sistem'),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->default(now())
                            ->required()
                            ->disabled(fn (Get $get): bool => $get('payment_method') === 'qris')
                            ->displayFormat('d M Y'),

                        Forms\Components\TextInput::make('transaction_ref')
                            ->label('Referensi Pembayaran')
                            ->maxLength(255)
                            ->disabled(fn (Get $get): bool => $get('payment_method') === 'qris')
                            ->helperText('Nomor referensi untuk transfer/QRIS/Tripay'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
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
        $user = Auth::user();
        $user = User::find($user->id);
        $isSuperAdmin = $user?->isSuperAdmin();
        $isCollector = $user?->role === 'collector';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bills.0.customer.village.name')
                    ->label('Desa')
                    ->searchable()
                    ->sortable()
                    ->visible($isSuperAdmin)
                    ->badge()
                    ->color('primary')
                    ->getStateUsing(fn($record) => $record->bills->first()?->customer->village->name),

                Tables\Columns\TextColumn::make('bills.0.customer.customer_code')
                    ->label('Kode Pelanggan')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn($record) => $record->bills->first()?->customer->customer_code),

                Tables\Columns\TextColumn::make('bills.0.customer.name')
                    ->label('Nama Pelanggan')
                    ->searchable()
                    ->limit(25)
                    ->sortable()
                    ->getStateUsing(fn($record) => $record->bills->first()?->customer->name),

                Tables\Columns\TextColumn::make('bills.0.waterUsage.billingPeriod.period_name')
                    ->label('Periode')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn($record) => $record->bills->first()?->waterUsage?->billingPeriod?->period_name),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Tanggal Bayar')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Jumlah Dibayar')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->colors([
                        'success' => 'cash',
                        'primary' => 'transfer',
                        'warning' => 'qris',
                        'gray' => 'other',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'cash' => 'Tunai',
                        'transfer' => 'Transfer',
                        'qris' => 'QRIS',
                        'other' => 'Lainnya',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'pending',
                        'danger' => 'expired',
                        'gray' => 'failed',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'completed' => 'Selesai',
                        'pending' => 'Menunggu',
                        'expired' => 'Kedaluwarsa',
                        'failed' => 'Gagal',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('collector.name')
                    ->label('Penagih')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('transaction_ref')
                    ->label('Referensi')
                    ->limit(20)
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('change_given')
                    ->label('Kembalian')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('village')
                    ->label('Desa')
                    ->options(\App\Models\Village::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $data['value']
                            ? $query->whereHas('bills.customer', fn($q) => $q->where('village_id', $data['value']))
                            : $query;
                    })
                    ->visible($isSuperAdmin),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options([
                        'cash' => 'Tunai',
                        'transfer' => 'Transfer Bank',
                        'qris' => 'QRIS',
                        'other' => 'Lainnya',
                    ])
                    ->searchable(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Pembayaran')
                    ->options([
                        'completed' => 'Selesai',
                        'pending' => 'Menunggu',
                        'expired' => 'Kedaluwarsa',
                        'failed' => 'Gagal',
                    ])
                    ->searchable(),

                Tables\Filters\SelectFilter::make('collector_id')
                    ->label('Penagih/Kasir')
                    ->relationship('collector', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(!$isCollector), // Collectors don't need this filter

                Tables\Filters\Filter::make('today')
                    ->label('Hari Ini')
                    ->query(fn(Builder $query) => $query->whereDate('payment_date', today())),

                Tables\Filters\Filter::make('this_week')
                    ->label('Minggu Ini')
                    ->query(fn(Builder $query) => $query->whereBetween('payment_date', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ])),

                Tables\Filters\Filter::make('this_month')
                    ->label('Bulan Ini')
                    ->query(fn(Builder $query) => $query->whereMonth('payment_date', now()->month)
                        ->whereYear('payment_date', now()->year)),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn(Payment $record): bool => static::canEdit($record)),
                    Tables\Actions\Action::make('print_receipt')
                        ->label('Cetak Kwitansi')
                        ->icon('heroicon-o-printer')
                        ->color('primary')
                        ->url(fn(Payment $record): string => "/admin/payments/{$record->payment_id}/receipt")
                        ->openUrlInNewTab(),
                ])
            ])
            ->headerActions([
                // Only admins can export
                ...($isCollector ? [] : static::getExportHeaderActions()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Only admins can bulk delete
                    ...($isCollector ? [] : [Tables\Actions\DeleteBulkAction::make()]),

                    Tables\Actions\BulkAction::make('bulk_print')
                        ->label('Cetak Kwitansi Terpilih')
                        ->icon('heroicon-o-printer')
                        ->color('primary')
                        ->action(function ($records) {
                            $urls = $records->map(fn(Payment $payment) =>
                            "/admin/payments/{$payment->payment_id}/receipt")->toArray();
                            return redirect()->back()->with('openUrls', $urls);
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Only admins can export
                    ...($isCollector ? [] : static::getExportBulkActions()),
                ]),
            ])
            ->defaultSort('payment_date', 'desc');
    }

    protected static function updateChangeGiven(callable $set, callable $get): void
    {
        $bill = \App\Models\Bill::find($get('bill_id'));

        if ($bill) {
            $totalAmount = $bill->total_amount;
            $amountPaid = $get('total_amount');

            if (is_numeric($amountPaid) && $amountPaid >= $totalAmount) {
                $set('change_given', $amountPaid - $totalAmount);
            } else {
                $set('change_given', 0);
            }
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            // Create removed - payments are made through bill pay actions only
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $user = User::find(Auth::user()->id);

        // Only show badges for collectors (their main KPI)
        if ($user?->role !== 'collector') {
            return null;
        }

        $currentVillage = $user?->getCurrentVillageContext();
        if (!$currentVillage) {
            return null;
        }

        // Show today's payment count for collectors
        $todayPayments = static::getEloquentQuery()
            ->whereDate('payment_date', today())
            ->where('collector_id', $user->id)
            ->count();

        return $todayPayments > 0 ? (string) $todayPayments : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
