<?php

namespace App\Filament\Resources\BundlePaymentResource\Pages;

use App\Filament\Resources\BundlePaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewBundlePayment extends ViewRecord
{
    protected static string $resource = BundlePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Pembayaran Bundel')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('bundle_reference')
                                    ->label('Referensi Bundel')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('customer.name')
                                    ->label('Pelanggan'),

                                Infolists\Components\TextEntry::make('customer.customer_code')
                                    ->label('Kode Pelanggan'),

                                Infolists\Components\TextEntry::make('total_amount')
                                    ->label('Total Pembayaran')
                                    ->money('IDR'),

                                Infolists\Components\TextEntry::make('bill_count')
                                    ->label('Jumlah Tagihan')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        'failed' => 'danger',
                                        'expired' => 'danger',
                                    }),

                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Metode Pembayaran')
                                    ->formatStateUsing(fn(string $state): string => match ($state) {
                                        'cash' => 'Tunai',
                                        'transfer' => 'Transfer Bank',
                                        'qris' => 'QRIS',
                                        'other' => 'Lainnya',
                                    }),

                                Infolists\Components\TextEntry::make('payment_reference')
                                    ->label('Referensi Pembayaran')
                                    ->placeholder('Tidak ada'),

                                Infolists\Components\TextEntry::make('collector.name')
                                    ->label('Petugas Penagih')
                                    ->placeholder('Tidak ada'),

                                Infolists\Components\TextEntry::make('paid_at')
                                    ->label('Tanggal Dibayar')
                                    ->dateTime()
                                    ->placeholder('Belum dibayar'),

                                Infolists\Components\TextEntry::make('expires_at')
                                    ->label('Tanggal Kadaluwarsa')
                                    ->dateTime()
                                    ->placeholder('Tidak ada'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Dibuat')
                                    ->dateTime(),
                            ]),

                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('Tidak ada catatan')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Detail Tagihan')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('bills')
                            ->label('')
                            ->schema([
                                Infolists\Components\Grid::make(4)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('waterUsage.billingPeriod.period_name')
                                            ->label('Periode'),

                                        Infolists\Components\TextEntry::make('waterUsage.total_usage_m3')
                                            ->label('Pemakaian')
                                            ->suffix(' m³'),

                                        Infolists\Components\TextEntry::make('total_amount')
                                            ->label('Jumlah')
                                            ->money('IDR'),

                                        Infolists\Components\TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn(string $state): string => match ($state) {
                                                'paid' => 'success',
                                                'unpaid' => 'warning',
                                                'overdue' => 'danger',
                                                'pending' => 'info',
                                            }),
                                    ]),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }
}