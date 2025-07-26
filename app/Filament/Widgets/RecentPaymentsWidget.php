<?php



// app/Filament/Widgets/RecentPaymentsWidget.php - Recent payments widget
namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RecentPaymentsWidget extends BaseWidget
{
    protected static ?string $heading = 'Pembayaran Terbaru';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        $user = User::find(Auth::id());
        $currentVillage = $user?->getCurrentVillageContext();

        if (!$currentVillage) {
            return $table->query(Payment::query()->whereRaw('1 = 0')); // Empty query
        }

        return $table
            ->query(
                Payment::whereHas('bills.customer', fn($q) => $q->where('village_id', $currentVillage))
                    ->with(['bills.customer', 'collector'])
                    ->latest('payment_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Tanggal')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bills.0.customer.customer_code')
                    ->label('Kode')
                    ->searchable()
                    ->getStateUsing(fn($record) => $record->bills->first()?->customer->customer_code),

                Tables\Columns\TextColumn::make('bills.0.customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->limit(25)
                    ->getStateUsing(fn($record) => $record->bills->first()?->customer->name),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Jumlah')
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
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('collector.name')
                    ->label('Petugas')
                    ->limit(20),
            ])
            ->defaultPaginationPageOption(10)
            ->poll('30s');
    }
}
