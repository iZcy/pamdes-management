<?php
// app/Filament/Resources/VariableResource.php - Village admin can manage Tripay settings

namespace App\Filament\Resources;

use App\Filament\Resources\VariableResource\Pages;
use App\Models\Variable;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class VariableResource extends Resource
{
    protected static ?string $model = Variable::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Pengaturan Sistem';
    protected static ?string $modelLabel = 'Pengaturan';
    protected static ?string $pluralModelLabel = 'Pengaturan';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationGroup = 'Pengaturan';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = User::find(Auth::user()->id);
        $currentVillage = $user?->getCurrentVillageContext();

        if ($user?->isSuperAdmin() && $currentVillage) {
            // Super admin sees settings for current village context
            $query->where('village_id', $currentVillage);
        } elseif ($user?->isVillageAdmin()) {
            // Village admin sees settings for their accessible villages
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            $query->whereIn('village_id', $accessibleVillages);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pengaturan Tripay')
                    ->description('Konfigurasi pembayaran digital QRIS melalui Tripay')
                    ->schema([
                        Forms\Components\Placeholder::make('village_info')
                            ->label('Desa')
                            ->content(function (?Variable $record) {
                                if ($record && $record->village) {
                                    return $record->village->name;
                                }
                                $user = User::find(Auth::user()->id);
                                $currentVillage = $user?->getCurrentVillageContext();
                                if ($currentVillage) {
                                    $village = \App\Models\Village::find($currentVillage);
                                    return $village?->name ?? 'Unknown Village';
                                }
                                return 'No Village Context';
                            })
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('village_id')
                            ->default(function () {
                                $user = User::find(Auth::user()->id);
                                return $user?->getCurrentVillageContext();
                            }),

                        Forms\Components\Toggle::make('tripay_use_main')
                            ->label('Gunakan Konfigurasi Global')
                            ->helperText('Jika diaktifkan, akan menggunakan konfigurasi Tripay global dari sistem')
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('tripay_is_production')
                            ->label('Mode Produksi')
                            ->helperText('Aktifkan untuk transaksi real, nonaktifkan untuk testing')
                            ->default(false)
                            ->visible(fn(Forms\Get $get) => !$get('tripay_use_main'))
                            ->columnSpanFull(),

                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('tripay_timeout_minutes')
                                ->label('Timeout Pembayaran (Menit)')
                                ->numeric()
                                ->default(15)
                                ->minValue(5)
                                ->maxValue(60)
                                ->helperText('Waktu timeout untuk pembayaran QRIS'),
                        ])
                            ->visible(fn(Forms\Get $get) => !$get('tripay_use_main'))
                            ->columns(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Kredensial Produksi')
                    ->description('Kredensial untuk transaksi real money')
                    ->schema([
                        Forms\Components\TextInput::make('tripay_api_key_prod')
                            ->label('API Key Produksi')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('API Key dari dashboard Tripay produksi'),

                        Forms\Components\TextInput::make('tripay_private_key_prod')
                            ->label('Private Key Produksi')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Private Key dari dashboard Tripay produksi'),

                        Forms\Components\TextInput::make('tripay_merchant_code_prod')
                            ->label('Merchant Code Produksi')
                            ->maxLength(255)
                            ->helperText('Kode merchant dari dashboard Tripay produksi'),
                    ])
                    ->visible(fn(Forms\Get $get) => !$get('tripay_use_main'))
                    ->columns(3),

                Forms\Components\Section::make('Kredensial Sandbox/Testing')
                    ->description('Kredensial untuk testing (tidak ada transaksi real)')
                    ->schema([
                        Forms\Components\TextInput::make('tripay_api_key_dev')
                            ->label('API Key Sandbox')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('API Key dari dashboard Tripay sandbox'),

                        Forms\Components\TextInput::make('tripay_private_key_dev')
                            ->label('Private Key Sandbox')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Private Key dari dashboard Tripay sandbox'),

                        Forms\Components\TextInput::make('tripay_merchant_code_dev')
                            ->label('Merchant Code Sandbox')
                            ->maxLength(255)
                            ->helperText('Kode merchant dari dashboard Tripay sandbox'),
                    ])
                    ->visible(fn(Forms\Get $get) => !$get('tripay_use_main'))
                    ->columns(3),

                Forms\Components\Section::make('URL Konfigurasi')
                    ->description('URL untuk callback dan return dari Tripay')
                    ->schema([
                        Forms\Components\TextInput::make('tripay_callback_url')
                            ->label('Callback URL')
                            ->url()
                            ->maxLength(255)
                            ->helperText('URL webhook untuk menerima notifikasi dari Tripay (kosongkan untuk default)'),

                        Forms\Components\TextInput::make('tripay_return_url')
                            ->label('Return URL')
                            ->url()
                            ->maxLength(255)
                            ->helperText('URL redirect setelah pembayaran (kosongkan untuk default)'),
                    ])
                    ->visible(fn(Forms\Get $get) => !$get('tripay_use_main'))
                    ->columns(2),

                Forms\Components\Section::make('Informasi')
                    ->schema([
                        Forms\Components\Placeholder::make('help_info')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="space-y-3 text-sm">
                                    <div class="bg-blue-50 p-3 rounded-lg">
                                        <h4 class="font-medium text-blue-800 mb-2">Cara Mendapatkan Kredensial Tripay:</h4>
                                        <ol class="list-decimal list-inside text-blue-700 space-y-1">
                                            <li>Daftar di <a href="https://tripay.co.id" target="_blank" class="underline">tripay.co.id</a></li>
                                            <li>Verifikasi akun dan lengkapi data merchant</li>
                                            <li>Masuk ke dashboard dan buka menu "API"</li>
                                            <li>Salin API Key, Private Key, dan Merchant Code</li>
                                            <li>Untuk testing, gunakan kredensial sandbox</li>
                                        </ol>
                                    </div>
                                    <div class="bg-yellow-50 p-3 rounded-lg">
                                        <h4 class="font-medium text-yellow-800 mb-2">Keamanan:</h4>
                                        <ul class="list-disc list-inside text-yellow-700 space-y-1">
                                            <li>Kredensial disimpan terenkripsi di database</li>
                                            <li>Gunakan mode sandbox untuk testing</li>
                                            <li>Aktifkan mode produksi hanya setelah testing berhasil</li>
                                        </ul>
                                    </div>
                                </div>
                            '))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $user = User::find($user->id);
        $isSuperAdmin = $user?->isSuperAdmin();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('village.name')
                    ->label('Desa')
                    ->searchable()
                    ->sortable()
                    ->visible($isSuperAdmin),

                Tables\Columns\IconColumn::make('tripay_use_main')
                    ->label('Gunakan Global')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('tripay_is_production')
                    ->label('Mode Produksi')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status Konfigurasi')
                    ->getStateUsing(function (Variable $record): string {
                        if ($record->tripay_use_main) {
                            return 'Menggunakan Global';
                        }
                        return $record->isConfigured() ? 'Terkonfigurasi' : 'Belum Lengkap';
                    })
                    ->badge()
                    ->color(fn(Variable $record): string => match (true) {
                        $record->tripay_use_main => 'primary',
                        $record->isConfigured() => 'success',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('tripay_timeout_minutes')
                    ->label('Timeout (Menit)')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diupdate')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('tripay_use_main')
                    ->label('Gunakan Global')
                    ->boolean()
                    ->trueLabel('Ya')
                    ->falseLabel('Tidak'),

                Tables\Filters\TernaryFilter::make('tripay_is_production')
                    ->label('Mode Produksi')
                    ->boolean()
                    ->trueLabel('Produksi')
                    ->falseLabel('Sandbox'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('test_connection')
                    ->label('Test Koneksi')
                    ->icon('heroicon-o-wifi')
                    ->color('info')
                    ->action(function (Variable $record) {
                        // Test Tripay connection
                        try {
                            $tripayService = new \App\Services\TripayService($record->village);
                            $channels = $tripayService->getPaymentChannels();

                            \Filament\Notifications\Notification::make()
                                ->title('Koneksi Berhasil')
                                ->body('Berhasil terhubung ke Tripay. Ditemukan ' . count($channels) . ' channel pembayaran.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Koneksi Gagal')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn(Variable $record) => $record->isConfigured()),
            ])
            ->emptyStateHeading('Belum Ada Pengaturan')
            ->emptyStateDescription('Buat pengaturan Tripay untuk desa Anda')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Buat Pengaturan'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVariables::route('/'),
            'create' => Pages\CreateVariable::route('/create'),
            'edit' => Pages\EditVariable::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        // Only allow creating if no settings exist for current village
        $user = User::find(Auth::user()->id);
        $currentVillage = $user?->getCurrentVillageContext();

        if (!$currentVillage) {
            return false;
        }

        return !Variable::where('village_id', $currentVillage)->exists();
    }

    public static function getNavigationBadge(): ?string
    {
        $user = User::find(Auth::user()->id);
        $currentVillage = $user?->getCurrentVillageContext();

        if (!$currentVillage) {
            return null;
        }

        $variable = Variable::where('village_id', $currentVillage)->first();

        if (!$variable) {
            return '!';
        }

        if (!$variable->tripay_use_main && !$variable->isConfigured()) {
            return '!';
        }

        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
