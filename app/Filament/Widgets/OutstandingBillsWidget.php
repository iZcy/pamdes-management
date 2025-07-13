<?php
// app/Filament/Widgets/OutstandingBillsWidget.php - Outstanding bills widget
namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Bill;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class OutstandingBillsWidget extends BaseWidget
{
    protected static ?string $heading = 'Tagihan Menunggak';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        $user = User::find(Auth::id());
        $currentVillage = $user?->getCurrentVillageContext();

        if (!$currentVillage) {
            return $table->query(Bill::query()->whereRaw('1 = 0')); // Empty query
        }

        return $table
            ->query(
                Bill::whereHas('waterUsage.customer', fn($q) => $q->where('village_id', $currentVillage))
                    ->where('status', '!=', 'paid')
                    ->with(['waterUsage.customer', 'waterUsage.billingPeriod'])
                    ->orderBy('due_date', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('waterUsage.customer.customer_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('waterUsage.customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('waterUsage.billingPeriod.period_name')
                    ->label('Periode')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => 'unpaid',
                        'danger' => 'overdue',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'unpaid' => 'Belum Bayar',
                        'overdue' => 'Terlambat',
                        default => $state,
                    }),
            ])
            ->defaultPaginationPageOption(10)
            ->poll('30s');
    }
}
