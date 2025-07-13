<?php

// app/Filament/Widgets/MonthlyRevenueChartWidget.php - Monthly revenue chart
namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MonthlyRevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Pendapatan 6 Bulan Terakhir';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $user = User::find(Auth::id());
        $currentVillage = $user?->getCurrentVillageContext();

        if (!$currentVillage) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $data = [];
        $labels = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthName = [
                1 => 'Jan',
                2 => 'Feb',
                3 => 'Mar',
                4 => 'Apr',
                5 => 'Mei',
                6 => 'Jun',
                7 => 'Jul',
                8 => 'Ags',
                9 => 'Sep',
                10 => 'Okt',
                11 => 'Nov',
                12 => 'Des'
            ][$month->month];

            $revenue = Payment::whereHas('bill.waterUsage.customer', fn($q) => $q->where('village_id', $currentVillage))
                ->whereMonth('payment_date', $month->month)
                ->whereYear('payment_date', $month->year)
                ->sum('amount_paid');

            $labels[] = $monthName . ' ' . $month->format('y');
            $data[] = $revenue / 1000000; // Convert to millions
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pendapatan (Juta Rp)',
                    'data' => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "Rp " + value + "M"; }',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
        ];
    }
}
