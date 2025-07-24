<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BundlePaymentResource\Pages;
use App\Models\BundlePayment;
use App\Models\User;
use App\Traits\ExportableResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BundlePaymentResource extends Resource
{
    use ExportableResource;

    protected static ?string $model = BundlePayment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Pembayaran Bundel';
    protected static ?string $modelLabel = 'Pembayaran Bundel';
    protected static ?string $pluralModelLabel = 'Pembayaran Bundel';
    protected static ?int $navigationSort = 7;
    protected static ?string $navigationGroup = 'Manajemen Pembayaran';

    public static function canCreate(): bool
    {
        $user = User::find(Auth::user()->id);
        return $user && !$user->isCollector();
    }

    public static function canEdit(Model $record): bool
    {
        $user = User::find(Auth::user()->id);
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canDelete(Model $record): bool
    {
        $user = User::find(Auth::user()->id);
        return $user && $user->isSuperAdmin() && $record->status !== 'paid';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['customer', 'collector', 'bills']);

        $user = User::find(Auth::user()->id);
        if ($user?->isSuperAdmin()) {
            $currentVillage = $user->getCurrentVillageContext();
            if ($currentVillage) {
                $query->forVillage($currentVillage);
            }
        } else {
            $accessibleVillages = $user?->getAccessibleVillages()->pluck('id') ?? collect();
            $query->whereHas('customer', function ($q) use ($accessibleVillages) {
                $q->whereIn('village_id', $accessibleVillages);
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pembayaran Bundel')
                    ->schema([
                        Forms\Components\TextInput::make('bundle_reference')
                            ->label('Referensi Bundel')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn() => BundlePayment::generateBundleReference())
                            ->disabled(fn(?BundlePayment $record) => $record?->exists),

                        Forms\Components\Select::make('customer_id')
                            ->label('Pelanggan')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn(?BundlePayment $record) => $record?->exists),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Pembayaran')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->disabled(),

                        Forms\Components\TextInput::make('bill_count')
                            ->label('Jumlah Tagihan')
                            ->numeric()
                            ->required()
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Menunggu Pembayaran',
                                'paid' => 'Lunas',
                                'failed' => 'Gagal',
                                'expired' => 'Kedaluwarsa',
                            ])
                            ->required()
                            ->disabled(fn(?BundlePayment $record) => $record?->status === 'paid'),

                        Forms\Components\Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options([
                                'cash' => 'Tunai',
                                'transfer' => 'Transfer Bank',
                                'qris' => 'QRIS',
                                'other' => 'Lainnya',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Referensi Pembayaran')
                            ->helperText('Referensi dari gateway pembayaran (Tripay, dll.)'),

                        Forms\Components\Select::make('collector_id')
                            ->label('Petugas Penagih')
                            ->relationship('collector', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('Tanggal Dibayar')
                            ->visible(fn(?BundlePayment $record) => $record?->status === 'paid'),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Tanggal Kadaluwarsa')
                            ->helperText('Untuk pembayaran QRIS dan gateway'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Detail Tagihan')
                    ->schema([
                        Forms\Components\Repeater::make('bills')
                            ->label('Tagihan dalam Bundel')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('bill_id')
                                    ->label('ID Tagihan')
                                    ->disabled(),
                                Forms\Components\TextInput::make('period_name')
                                    ->label('Periode')
                                    ->default(fn($record) => $record?->waterUsage?->billingPeriod?->period_name)
                                    ->disabled(),
                                Forms\Components\TextInput::make('total_amount')
                                    ->label('Jumlah')
                                    ->prefix('Rp')
                                    ->disabled(),
                            ])
                            ->columns(3)
                            ->disabled()
                            ->visible(fn(?BundlePayment $record) => $record?->exists),
                    ])
                    ->visible(fn(?BundlePayment $record) => $record?->exists),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $user = User::find($user->id);
        $isSuperAdmin = $user?->isSuperAdmin();
        $isCollector = $user?->isCollector();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.village.name')
                    ->label('Desa')
                    ->searchable()
                    ->sortable()
                    ->visible($isSuperAdmin)
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('bundle_reference')
                    ->label('Referensi')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('customer.customer_code')
                    ->label('Kode Pelanggan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bill_count')
                    ->label('Jumlah Tagihan')
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'expired' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'paid' => 'Lunas',
                        'pending' => 'Menunggu',
                        'failed' => 'Gagal',
                        'expired' => 'Kedaluwarsa',
                    }),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'cash' => 'success',
                        'qris' => 'info',
                        'transfer' => 'warning',
                        'other' => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'cash' => 'Tunai',
                        'transfer' => 'Transfer',
                        'qris' => 'QRIS',
                        'other' => 'Lainnya',
                    }),

                Tables\Columns\TextColumn::make('collector.name')
                    ->label('Petugas')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('Tidak ada'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Dibayar')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Belum dibayar'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Menunggu Pembayaran',
                        'paid' => 'Lunas',
                        'failed' => 'Gagal',
                        'expired' => 'Kedaluwarsa',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options([
                        'cash' => 'Tunai',
                        'transfer' => 'Transfer Bank',
                        'qris' => 'QRIS',
                        'other' => 'Lainnya',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Dibuat Dari'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Dibuat Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    Tables\Actions\Action::make('markAsPaid')
                        ->label('Tandai Lunas')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Tandai Sebagai Lunas')
                        ->modalDescription('Apakah Anda yakin ingin menandai pembayaran bundel ini sebagai lunas?')
                        ->action(function (BundlePayment $record) {
                            $record->markAsPaid();
                        })
                        ->visible(fn(BundlePayment $record) => $record->canBePaid())
                        ->visible(fn() => !$isCollector),

                    Tables\Actions\Action::make('markAsFailed')
                        ->label('Tandai Gagal')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (BundlePayment $record) {
                            $record->markAsFailed();
                        })
                        ->visible(fn(BundlePayment $record) => $record->status === 'pending')
                        ->visible(fn() => !$isCollector),

                    Tables\Actions\EditAction::make()
                        ->visible(fn() => !$isCollector),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => !$isCollector),
                    
                    ...(!$isCollector ? static::getExportBulkActions() : []),
                ]),
            ])
            ->headerActions([
                ...(!$isCollector ? static::getExportHeaderActions() : []),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBundlePayments::route('/'),
            'create' => Pages\CreateBundlePayment::route('/create'),
            'view' => Pages\ViewBundlePayment::route('/{record}'),
            'edit' => Pages\EditBundlePayment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $user = User::find(Auth::user()->id);
        if (!$user) return null;

        $query = static::getEloquentQuery()->where('status', 'pending');
        
        return $query->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}