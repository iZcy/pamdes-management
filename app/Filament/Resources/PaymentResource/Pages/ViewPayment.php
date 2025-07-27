<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Pembayaran')
                    ->schema([
                        TextEntry::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->date(),
                        TextEntry::make('total_amount')
                            ->label('Total Pembayaran')
                            ->money('IDR')
                            ->weight('bold'),
                        TextEntry::make('change_given')
                            ->label('Kembalian')
                            ->money('IDR')
                            ->visible(fn ($record) => $record->change_given > 0),
                        TextEntry::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'cash' => 'Tunai',
                                'transfer' => 'Transfer Bank',
                                'qris' => 'QRIS',
                                'other' => 'Lainnya',
                                default => ucfirst($state),
                            })
                            ->colors([
                                'success' => 'cash',
                                'info' => 'transfer',
                                'warning' => 'qris',
                                'gray' => 'other',
                            ]),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending' => 'Menunggu',
                                'completed' => 'Selesai',
                                'expired' => 'Kedaluwarsa',
                                default => ucfirst($state),
                            })
                            ->colors([
                                'warning' => 'pending',
                                'success' => 'completed',
                                'danger' => 'expired',
                            ]),
                        TextEntry::make('collector.name')
                            ->label('Penagih/Kasir')
                            ->visible(fn ($record) => $record->collector_id),
                    ])
                    ->columns(2),

                Section::make('Referensi Transaksi')
                    ->schema([
                        TextEntry::make('transaction_ref')
                            ->label('Referensi Transaksi')
                            ->copyable()
                            ->weight('bold')
                            ->placeholder('Tidak ada referensi'),
                        TextEntry::make('tripay_data.reference')
                            ->label('Referensi Tripay')
                            ->visible(fn ($record) => $record->tripay_data && isset($record->tripay_data['reference']))
                            ->copyable(),
                        TextEntry::make('tripay_data.status')
                            ->label('Status Tripay')
                            ->visible(fn ($record) => $record->tripay_data && isset($record->tripay_data['status']))
                            ->badge(),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record->transaction_ref || $record->tripay_data)
                    ->collapsible(),

                Section::make('Tagihan yang Dibayar')
                    ->schema([
                        RepeatableEntry::make('bills')
                            ->label('')
                            ->schema([
                                TextEntry::make('waterUsage.customer.customer_code')
                                    ->label('Kode Pelanggan'),
                                TextEntry::make('waterUsage.customer.name')
                                    ->label('Nama Pelanggan'),
                                TextEntry::make('waterUsage.billingPeriod.period_name')
                                    ->label('Periode'),
                                TextEntry::make('total_amount')
                                    ->label('Jumlah Tagihan')
                                    ->money('IDR'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->colors([
                                        'warning' => 'unpaid',
                                        'success' => 'paid',
                                        'danger' => 'overdue',
                                    ])
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'unpaid' => 'Belum Bayar',
                                        'paid' => 'Lunas',
                                        'overdue' => 'Terlambat',
                                        default => ucfirst($state),
                                    }),
                                TextEntry::make('due_date')
                                    ->label('Jatuh Tempo')
                                    ->date(),
                            ])
                            ->columns(6)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Catatan')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('')
                            ->placeholder('Tidak ada catatan')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->notes)
                    ->collapsible(),
            ]);
    }
}
