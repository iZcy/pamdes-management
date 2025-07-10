<?php
// app/Filament/Resources/BillingPeriodResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingPeriodResource\Pages;
use App\Models\BillingPeriod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BillingPeriodResource extends Resource
{
    protected static ?string $model = BillingPeriod::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Periode Tagihan';
    protected static ?string $modelLabel = 'Periode Tagihan';
    protected static ?string $pluralModelLabel = 'Periode Tagihan';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Periode Tagihan')
                    ->schema([
                        Forms\Components\Select::make('month')
                            ->label('Bulan')
                            ->options([
                                1 => 'Januari',
                                2 => 'Februari',
                                3 => 'Maret',
                                4 => 'April',
                                5 => 'Mei',
                                6 => 'Juni',
                                7 => 'Juli',
                                8 => 'Agustus',
                                9 => 'September',
                                10 => 'Oktober',
                                11 => 'November',
                                12 => 'Desember'
                            ])
                            ->required()
                            ->default(now()->month),

                        Forms\Components\TextInput::make('year')
                            ->label('Tahun')
                            ->required()
                            ->numeric()
                            ->minValue(2020)
                            ->maxValue(2030)
                            ->default(now()->year),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'inactive' => 'Tidak Aktif',
                                'active' => 'Aktif',
                                'completed' => 'Selesai',
                            ])
                            ->default('inactive')
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Jadwal')
                    ->schema([
                        Forms\Components\DatePicker::make('reading_start_date')
                            ->label('Tanggal Mulai Baca Meter'),

                        Forms\Components\DatePicker::make('reading_end_date')
                            ->label('Tanggal Selesai Baca Meter'),

                        Forms\Components\DatePicker::make('billing_due_date')
                            ->label('Tanggal Jatuh Tempo'),

                        Forms\Components\Hidden::make('village_id')
                            ->default(fn() => request()->get('village_id')),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period_name')
                    ->label('Periode')
                    ->sortable(['year', 'month']),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'inactive',
                        'success' => 'active',
                        'primary' => 'completed',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'inactive' => 'Tidak Aktif',
                        'active' => 'Aktif',
                        'completed' => 'Selesai',
                    }),

                Tables\Columns\TextColumn::make('total_customers')
                    ->label('Jumlah Pelanggan')
                    ->numeric(),

                Tables\Columns\TextColumn::make('total_billed')
                    ->label('Total Tagihan')
                    ->money('IDR'),

                Tables\Columns\TextColumn::make('collection_rate')
                    ->label('Tingkat Penagihan')
                    ->formatStateUsing(fn($state) => number_format($state, 1) . '%'),

                Tables\Columns\TextColumn::make('billing_due_date')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'inactive' => 'Tidak Aktif',
                        'active' => 'Aktif',
                        'completed' => 'Selesai',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate_bills')
                    ->label('Generate Tagihan')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->visible(fn(BillingPeriod $record): bool => $record->status === 'active')
                    ->action(function (BillingPeriod $record) {
                        // Logic to generate bills for this period
                        // This will be implemented later
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('year', 'desc')
            ->defaultSort('month', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillingPeriods::route('/'),
            'create' => Pages\CreateBillingPeriod::route('/create'),
            'view' => Pages\ViewBillingPeriod::route('/{record}'),
            'edit' => Pages\EditBillingPeriod::route('/{record}/edit'),
        ];
    }
}
