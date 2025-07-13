<?php



// app/Filament/Widgets/TopWaterUsageWidget.php - Top water usage widget
namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\WaterUsage;
use App\Models\BillingPeriod;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class TopWaterUsageWidget extends BaseWidget
{
    protected static ?string $heading = 'Top 10 Pemakaian Air (Periode Aktif)';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        $user = User::find(Auth::id());
        $currentVillage = $user?->getCurrentVillageContext();

        if (!$currentVillage) {
            return $table->query(WaterUsage::query()->whereRaw('1 = 0')); // Empty query
        }

        // Find active period for current village
        $activePeriod = BillingPeriod::where('village_id', $currentVillage)
            ->where('status', 'active')
            ->first();

        if (!$activePeriod) {
            return $table->query(WaterUsage::query()->whereRaw('1 = 0')); // Empty query
        }

        return $table
            ->query(
                WaterUsage::where('period_id', $activePeriod->period_id)
                    ->with('customer')
                    ->orderBy('total_usage_m3', 'desc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('ranking')
                    ->label('#')
                    ->getStateUsing(function ($record, $rowLoop) {
                        $rank = $rowLoop->iteration;
                        return match ($rank) {
                            1 => '🥇',
                            2 => '🥈',
                            3 => '🥉',
                            default => $rank
                        };
                    })
                    ->width('60px'),

                Tables\Columns\TextColumn::make('customer.customer_code')
                    ->label('Kode')
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('total_usage_m3')
                    ->label('Pemakaian')
                    ->suffix(' m³')
                    ->numeric()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('initial_meter')
                    ->label('Meter Awal')
                    ->numeric(),

                Tables\Columns\TextColumn::make('final_meter')
                    ->label('Meter Akhir')
                    ->numeric(),

                Tables\Columns\TextColumn::make('usage_date')
                    ->label('Tanggal Baca')
                    ->date(),
            ])
            ->paginated(false);
    }

    public function getTableEmptyStateHeading(): ?string
    {
        return 'Belum Ada Data Pemakaian';
    }

    public function getTableEmptyStateDescription(): ?string
    {
        return 'Pastikan ada periode tagihan yang berstatus "aktif" dan sudah ada pembacaan meter air.';
    }
}
