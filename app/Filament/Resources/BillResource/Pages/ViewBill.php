<?php

namespace App\Filament\Resources\BillResource\Pages;

use App\Filament\Resources\BillResource;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewBill extends ViewRecord
{
    protected static string $resource = BillResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Tagihan')
                    ->schema([
                        TextEntry::make('waterUsage.customer.customer_code')
                            ->label('Kode Pelanggan'),
                        TextEntry::make('waterUsage.customer.name')
                            ->label('Nama Pelanggan'),
                        TextEntry::make('waterUsage.billingPeriod.period_name')
                            ->label('Periode Tagihan'),
                        TextEntry::make('waterUsage.water_used')
                            ->label('Pemakaian Air')
                            ->suffix(' m³'),
                        TextEntry::make('water_charge')
                            ->label('Biaya Air')
                            ->money('IDR'),
                        TextEntry::make('admin_fee')
                            ->label('Biaya Admin')
                            ->money('IDR'),
                        TextEntry::make('maintenance_fee')
                            ->label('Biaya Pemeliharaan')
                            ->money('IDR'),
                        TextEntry::make('total_amount')
                            ->label('Total Tagihan')
                            ->money('IDR')
                            ->weight('bold'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->colors([
                                'warning' => 'unpaid',
                                'success' => 'paid',
                                'danger' => 'overdue',
                            ]),
                        TextEntry::make('due_date')
                            ->label('Jatuh Tempo')
                            ->date(),
                        TextEntry::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->date()
                            ->visible(fn ($record) => $record->status === 'paid'),
                    ])
                    ->columns(2),

                Section::make('Informasi Bundel')
                    ->schema([
                        TextEntry::make('transaction_ref')
                            ->label('Transaction Reference')
                            ->copyable()
                            ->weight('bold')
                            ->visible(fn ($record) => $record->transaction_ref),
                        TextEntry::make('expires_at')
                            ->label('Kadaluwarsa')
                            ->dateTime()
                            ->visible(fn ($record) => $record->status === 'pending' && $record->expires_at),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record->is_bundle)
                    ->collapsible(),

                Section::make('Tagihan dalam Bundel')
                    ->schema([
                        RepeatableEntry::make('bundledBills')
                            ->label('')
                            ->schema([
                                TextEntry::make('waterUsage.customer.customer_code')
                                    ->label('Kode Pelanggan'),
                                TextEntry::make('waterUsage.billingPeriod.period_name')
                                    ->label('Periode'),
                                TextEntry::make('total_amount')
                                    ->label('Jumlah')
                                    ->money('IDR'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->colors([
                                        'warning' => 'unpaid',
                                        'success' => 'paid',
                                        'danger' => 'overdue',
                                    ]),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->is_bundle && $record->bundledBills()->exists())
                    ->collapsible(),
            ]);
    }
}
