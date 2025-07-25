<?php

namespace App\Filament\Resources\BillResource\Pages;

use App\Filament\Resources\BillResource;
use App\Models\BundlePayment;
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

                Section::make('Bundel Pembayaran')
                    ->schema([
                        RepeatableEntry::make('bundlePayments')
                            ->label('')
                            ->schema([
                                TextEntry::make('bundle_reference')
                                    ->label('Referensi Bundel')
                                    ->copyable()
                                    ->weight('bold'),
                                TextEntry::make('status')
                                    ->label('Status Bundel')
                                    ->badge()
                                    ->colors([
                                        'warning' => 'pending',
                                        'success' => 'paid',
                                        'danger' => ['failed', 'expired'],
                                    ])
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        'pending' => 'Menunggu Pembayaran',
                                        'paid' => 'Lunas',
                                        'failed' => 'Gagal',
                                        'expired' => 'Kedaluwarsa',
                                        default => $state
                                    }),
                                TextEntry::make('total_amount')
                                    ->label('Total Bundel')
                                    ->money('IDR'),
                                TextEntry::make('bill_count')
                                    ->label('Jumlah Tagihan')
                                    ->suffix(' tagihan'),
                                TextEntry::make('payment_method')
                                    ->label('Metode Pembayaran')
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        'cash' => 'Tunai',
                                        'transfer' => 'Transfer Bank',
                                        'qris' => 'QRIS',  
                                        'other' => 'Lainnya',
                                        default => $state
                                    }),
                                TextEntry::make('collector.name')
                                    ->label('Petugas Penagih')
                                    ->visible(fn ($record) => $record->collector_id),
                                TextEntry::make('paid_at')
                                    ->label('Dibayar Pada')
                                    ->dateTime()
                                    ->visible(fn ($record) => $record->status === 'paid'),
                                TextEntry::make('expires_at')
                                    ->label('Kadaluwarsa')
                                    ->dateTime()
                                    ->visible(fn ($record) => $record->status === 'pending'),
                                TextEntry::make('notes')
                                    ->label('Catatan')
                                    ->visible(fn ($record) => $record->notes),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->bundlePayments()->exists())
                    ->collapsible(),
            ]);
    }
}
