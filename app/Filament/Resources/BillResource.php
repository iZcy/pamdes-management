<?php
// app/Filament/Resources/BillResource.php - Updated for village context

namespace App\Filament\Resources;

use App\Filament\Resources\BillResource\Pages;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\User;
use App\Models\WaterUsage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BillResource extends Resource
{
    protected static ?string $model = Bill::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Tagihan';
    protected static ?string $modelLabel = 'Tagihan';
    protected static ?string $pluralModelLabel = 'Tagihan';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationGroup = 'Tagihan & Pembayaran';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

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
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Tagihan')
                    ->schema([
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
                                $village = \App\Models\Village::find(User::find(Auth::user()->id)?->getCurrentVillageContext());
                                return $village?->getDefaultAdminFee() ?? 5000;
                            }),

                        Forms\Components\TextInput::make('maintenance_fee')
                            ->label('Biaya Pemeliharaan')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->default(function () {
                                $village = \App\Models\Village::find(User::find(Auth::user()->id)?->getCurrentVillageContext());
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
                            ->default('unpaid')
                            ->required(),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('Tanggal Jatuh Tempo')
                            ->required(),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->visible(fn(Forms\Get $get) => $get('status') === 'paid'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('waterUsage.total_usage_m3')
                    ->label('Pemakaian')
                    ->suffix(' m³')
                    ->numeric(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Tagihan')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'unpaid',
                        'success' => 'paid',
                        'danger' => 'overdue',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'unpaid' => 'Belum Bayar',
                        'paid' => 'Sudah Bayar',
                        'overdue' => 'Terlambat',
                    }),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'unpaid' => 'Belum Bayar',
                        'paid' => 'Sudah Bayar',
                        'overdue' => 'Terlambat',
                    ]),

                Tables\Filters\Filter::make('overdue')
                    ->label('Tagihan Terlambat')
                    ->query(fn($query) => $query->overdue()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('mark_paid')
                    ->label('Tandai Lunas')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Bill $record): bool => $record->canBePaid())
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
                            ->default('cash')
                            ->required(),
                        Forms\Components\TextInput::make('collector_name')
                            ->label('Nama Penagih'),
                    ])
                    ->action(function (Bill $record, array $data) {
                        $record->markAsPaid($data);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBills::route('/'),
            'create' => Pages\CreateBill::route('/create'),
            'view' => Pages\ViewBill::route('/{record}'),
            'edit' => Pages\EditBill::route('/{record}/edit'),
        ];
    }
}
