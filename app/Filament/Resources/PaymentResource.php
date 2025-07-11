<?php
// app/Filament/Resources/PaymentResource.php - Updated with village display

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Pembayaran';
    protected static ?string $modelLabel = 'Pembayaran';
    protected static ?string $pluralModelLabel = 'Pembayaran';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationGroup = 'Tagihan & Pembayaran';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['bill.waterUsage.customer.village']);

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
                                    return $record->bill->waterUsage->customer->village;
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
                            ->required(),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('amount_paid')
                            ->label('Jumlah Dibayar')
                            ->required()
                            ->numeric()
                            ->prefix('Rp'),

                        Forms\Components\TextInput::make('change_given')
                            ->label('Kembalian')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0),

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

                        Forms\Components\TextInput::make('collector_name')
                            ->label('Nama Penagih/Kasir')
                            ->maxLength(255),

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
                    ->searchable(),

                Tables\Columns\TextColumn::make('bill.waterUsage.customer.name')
                    ->label('Nama Pelanggan')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('bill.waterUsage.billingPeriod.period_name')
                    ->label('Periode')
                    ->searchable(),

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

                Tables\Columns\TextColumn::make('collector_name')
                    ->label('Penagih')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Referensi')
                    ->limit(20)
                    ->toggleable(),

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

                Tables\Filters\Filter::make('today')
                    ->label('Hari Ini')
                    ->query(fn($query) => $query->today()),

                Tables\Filters\Filter::make('this_month')
                    ->label('Bulan Ini')
                    ->query(fn($query) => $query->thisMonth()),
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('payment_date', 'desc');
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
}
