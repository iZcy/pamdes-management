<?php

// app/Filament/Resources/WaterUsageResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\WaterUsageResource\Pages;
use App\Models\WaterUsage;
use App\Models\Customer;
use App\Models\BillingPeriod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WaterUsageResource extends Resource
{
    protected static ?string $model = WaterUsage::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Pembacaan Meter';
    protected static ?string $modelLabel = 'Pembacaan Meter';
    protected static ?string $pluralModelLabel = 'Pembacaan Meter';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationGroup = 'Manajemen Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pembacaan Meter Air')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Pelanggan')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(fn(Customer $record) => "{$record->customer_code} - {$record->name}"),

                        Forms\Components\Select::make('period_id')
                            ->label('Periode Tagihan')
                            ->relationship('billingPeriod', 'period_name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('initial_meter')
                            ->label('Meter Awal')
                            ->required()
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('final_meter')
                            ->label('Meter Akhir')
                            ->required()
                            ->numeric()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state, Forms\Get $get) {
                                $initial = $get('initial_meter') ?? 0;
                                $final = $state ?? 0;
                                $set('total_usage_m3', max(0, $final - $initial));
                            }),

                        Forms\Components\TextInput::make('total_usage_m3')
                            ->label('Total Pemakaian (m³)')
                            ->numeric()
                            ->readOnly(),

                        Forms\Components\DatePicker::make('usage_date')
                            ->label('Tanggal Baca')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('reader_name')
                            ->label('Nama Pembaca')
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
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.customer_code')
                    ->label('Kode Pelanggan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Nama Pelanggan')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('billingPeriod.period_name')
                    ->label('Periode')
                    ->sortable(),

                Tables\Columns\TextColumn::make('initial_meter')
                    ->label('Meter Awal')
                    ->numeric(),

                Tables\Columns\TextColumn::make('final_meter')
                    ->label('Meter Akhir')
                    ->numeric(),

                Tables\Columns\TextColumn::make('total_usage_m3')
                    ->label('Pemakaian (m³)')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('usage_date')
                    ->label('Tanggal Baca')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reader_name')
                    ->label('Pembaca')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('bill')
                    ->label('Sudah Dibill')
                    ->boolean()
                    ->getStateUsing(fn(WaterUsage $record): bool => $record->bill !== null)
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('period_id')
                    ->label('Periode')
                    ->relationship('billingPeriod', 'period_name'),

                Tables\Filters\Filter::make('has_bill')
                    ->label('Sudah Dibill')
                    ->query(fn($query) => $query->whereHas('bill')),

                Tables\Filters\Filter::make('no_bill')
                    ->label('Belum Dibill')
                    ->query(fn($query) => $query->whereDoesntHave('bill')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate_bill')
                    ->label('Buat Tagihan')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->visible(fn(WaterUsage $record): bool => $record->bill === null)
                    ->action(function (WaterUsage $record) {
                        $record->generateBill([
                            'admin_fee' => 5000,
                            'maintenance_fee' => 2000,
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('usage_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaterUsages::route('/'),
            'create' => Pages\CreateWaterUsage::route('/create'),
            'view' => Pages\ViewWaterUsage::route('/{record}'),
            'edit' => Pages\EditWaterUsage::route('/{record}/edit'),
        ];
    }
}
