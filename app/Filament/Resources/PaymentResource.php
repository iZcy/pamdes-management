<?php
// app/Filament/Resources/PaymentResource.php - Updated with collector integration

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Models\User;
use App\Models\Collector;
use App\Models\Village;
use App\Traits\ExportableResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PaymentResource extends Resource
{
    use ExportableResource; // Add this trait

    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Pembayaran';
    protected static ?string $modelLabel = 'Pembayaran';
    protected static ?string $pluralModelLabel = 'Pembayaran';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationGroup = 'Tagihan & Pembayaran';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['bill.waterUsage.customer.village', 'collector']);

        $user = User::find(Auth::user()->id);
        $currentVillage = $user?->getCurrentVillageContext();

        if ($user?->isSuperAdmin() && $currentVillage) {
            $query->whereHas('bill.waterUsage.customer', function ($q) use ($currentVillage) {
                $q->where('village_id', $currentVillage);
            });
        } elseif ($user?->isVillageAdmin()) {
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            $query->whereHas('bill.waterUsage.customer', function ($q) use ($accessibleVillages) {
                $q->whereIn('village_id', $accessibleVillages);
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pembayaran')
                    ->schema([
                        Forms\Components\Placeholder::make('village_info')
                            ->label('Desa')
                            ->content(function (?Payment $record) {
                                if ($record && $record->bill?->waterUsage?->customer?->village) {
                                    $village = Village::find($record->bill->waterUsage->customer->village_id);
                                    return $village?->name ?? 'Unknown Village';
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
                            ->columnSpan(2),

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
                            ->allowHtml()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Lengkap')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->unique('users', 'email'),

                                Forms\Components\TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->required()
                                    ->minLength(8),

                                Forms\Components\TextInput::make('contact_info')
                                    ->label('Nomor Telepon')
                                    ->tel()
                                    ->maxLength(20),

                                Forms\Components\Select::make('role')
                                    ->label('Peran')
                                    ->options([
                                        'collector' => 'Penagih',
                                        'operator' => 'Operator',
                                    ])
                                    ->default('collector')
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $user = Auth::user();
                                $user = User::find($user->id);
                                $currentVillage = $user?->getCurrentVillageContext();

                                if (!$currentVillage) {
                                    throw new \Exception('Village context not found');
                                }

                                $newUser = User::create([
                                    'name' => $data['name'],
                                    'email' => $data['email'],
                                    'password' => Hash::make($data['password']),
                                    'contact_info' => $data['contact_info'] ?? null,
                                    'role' => $data['role'],
                                    'is_active' => true,
                                ]);

                                // Assign to current village
                                $newUser->assignToVillage($currentVillage, false);

                                return $newUser->id;
                            }),
                        Forms\Components\TextInput::make('amount_paid')
                            ->label('Jumlah Dibayar')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->step(100),

                        Forms\Components\TextInput::make('change_given')
                            ->label('Kembalian')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->step(100),

                        Forms\Components\Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options([
                                'cash' => 'Tunai',
                                'transfer' => 'Transfer Bank',
                                'qris' => 'QRIS',
                                'other' => 'Lainnya',
                            ])
                            ->default('cash')
                            ->required(),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Referensi Pembayaran')
                            ->maxLength(255)
                            ->helperText('Nomor referensi untuk transfer/QRIS'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('bill.waterUsage.customer.village.name')
                    ->label('Desa')
                    ->searchable()
                    ->sortable()
                    ->visible($isSuperAdmin)
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('bill.waterUsage.customer.customer_code')
                    ->label('Kode Pelanggan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bill.waterUsage.customer.name')
                    ->label('Nama Pelanggan')
                    ->searchable()
                    ->limit(25)
                    ->sortable(),

                Tables\Columns\TextColumn::make('bill.waterUsage.billingPeriod.period_name')
                    ->label('Periode')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Tanggal Bayar')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_paid')
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

                Tables\Columns\TextColumn::make('collector.name')
                    ->label('Penagih')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Referensi')
                    ->limit(20)
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('change_given')
                    ->label('Kembalian')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dicatat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('village')
                    ->label('Desa')
                    ->relationship('bill.waterUsage.customer.village', 'name')
                    ->visible($isSuperAdmin),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options([
                        'cash' => 'Tunai',
                        'transfer' => 'Transfer Bank',
                        'qris' => 'QRIS',
                        'other' => 'Lainnya',
                    ]),

                Tables\Filters\SelectFilter::make('collector_id')
                    ->label('Penagih/Kasir')
                    ->relationship('collector', 'name')
                    ->searchable()
                    ->preload(),

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

                Tables\Filters\Filter::make('has_change')
                    ->label('Ada Kembalian')
                    ->query(fn(Builder $query) => $query->where('change_given', '>', 0)),

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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('print_receipt')
                    ->label('Cetak Kwitansi')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->url(fn(Payment $record): string => "/admin/payments/{$record->payment_id}/receipt")
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                ...static::getExportHeaderActions(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('export_daily_report')
                        ->label('Ekspor Laporan Harian')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function ($records) {
                            // Implementation for daily report export
                            // This would generate a PDF or Excel file
                        }),
                    ...static::getExportBulkActions(),
                ]),
            ])
            ->defaultSort('payment_date', 'desc')
            ->poll('30s') // Auto-refresh every 30 seconds for real-time updates
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $user = User::find(Auth::user()->id);
        $currentVillage = $user?->getCurrentVillageContext();

        if (!$currentVillage) {
            return null;
        }

        // Show today's payment count
        $todayPayments = static::getEloquentQuery()
            ->whereDate('payment_date', today())
            ->count();

        return $todayPayments > 0 ? (string) $todayPayments : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
