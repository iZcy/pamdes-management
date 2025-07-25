<?php
// app/Filament/Resources/VariableResource.php - Village admin can manage Tripay settings

namespace App\Filament\Resources;

use App\Filament\Resources\VariableResource\Pages;
use App\Models\Variable;
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

class VariableResource extends Resource
{
    use ExportableResource;

    protected static ?string $model = Variable::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Pengaturan Sistem';
    protected static ?string $modelLabel = 'Pengaturan';
    protected static ?string $pluralModelLabel = 'Pengaturan';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationGroup = 'Pengaturan';

    // Role-based navigation visibility
    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Collectors and operators cannot see system settings
        if ($user && in_array($user->role, ['collector', 'operator'])) {
            return false;
        }

        return true;
    }

    public static function canCreate(): bool
    {
        $user = User::find(Auth::user()->id);

        if (!$user || !in_array($user->role, ['super_admin', 'village_admin'])) {
            return false;
        }

        $currentVillage = $user->getCurrentVillageContext();

        if (!$currentVillage) {
            return false;
        }

        return !Variable::where('village_id', $currentVillage)->exists();
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Only super_admin and village_admin can edit settings
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canDelete(Model $record): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Only super_admin and village_admin can delete settings
        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canDeleteAny(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        return $user && in_array($user->role, ['super_admin', 'village_admin']);
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        $user = User::find($user->id);

        // Collectors and operators cannot view system settings
        return $user && !in_array($user->role, ['collector', 'operator']);
    }

    /**
     * Get the current village ID with proper fallback logic
     */
    protected static function getCurrentVillageId(): ?string
    {
        $user = User::find(Auth::user()->id);

        if (!$user) {
            return null;
        }

        // For super admins, use village context if available
        if ($user->isSuperAdmin()) {
            return $user->getCurrentVillageContext();
        }

        // For village users, try multiple approaches
        $villageId = $user->getCurrentVillageContext();

        // If still no village, try fallbacks
        if (!$villageId) {
            // Try config directly
            $villageId = config('pamdes.current_village_id');

            if (!$villageId) {
                // Try primary village
                $villageId = $user->getPrimaryVillageId();
            }

            if (!$villageId) {
                // Try first accessible village
                $firstVillage = $user->getAccessibleVillages()->first();
                $villageId = $firstVillage?->id;
            }
        }

        // Verify user has access to this village
        if ($villageId && !$user->hasAccessToVillage($villageId)) {
            // Fall back to first accessible village
            $firstVillage = $user->getAccessibleVillages()->first();
            $villageId = $firstVillage?->id;
        }

        return $villageId;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = User::find(Auth::user()->id);
        $currentVillage = static::getCurrentVillageId();

        if ($user?->isSuperAdmin() && $currentVillage) {
            // Super admin sees settings for current village context
            $query->where('village_id', $currentVillage);
        } elseif ($user?->isVillageAdmin()) {
            // Village admin sees settings for their accessible villages
            $accessibleVillages = $user->getAccessibleVillages()->pluck('id');
            if ($accessibleVillages->isNotEmpty()) {
                $query->whereIn('village_id', $accessibleVillages);
            } else {
                // If no accessible villages, return empty result
                $query->whereRaw('1 = 0');
            }
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
                                $currentVillageId = static::getCurrentVillageId();
                                if ($currentVillageId) {
                                    $village = \App\Models\Village::find($currentVillageId);
                                    return $village?->name ?? 'Unknown Village';
                                }
                                return 'No Village Context';
                            })
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('village_id')
                            ->default(function () {
                                $user = User::find(Auth::user()->id);
                                $currentVillageId = static::getCurrentVillageId();
                                
                                // For all roles, use current village context or fallback
                                if ($currentVillageId) {
                                    return $currentVillageId;
                                }
                                
                                // Fallback to first accessible village for village admin
                                $firstVillage = $user?->getAccessibleVillages()->first();
                                return $firstVillage?->id;
                            }),

                        Forms\Components\Group::make([
                            Forms\Components\Toggle::make('tripay_use_main')
                                ->label('Gunakan Konfigurasi Global')
                                ->helperText('Jika diaktifkan, akan menggunakan konfigurasi Tripay global dari sistem')
                                ->default(true)
                                ->live()
                                ->columnSpan(1),

                            Forms\Components\Toggle::make('tripay_is_production')
                                ->label('Mode Produksi')
                                ->helperText('Aktifkan untuk transaksi real, nonaktifkan untuk testing')
                                ->default(false)
                                ->columnSpan(1),
                        ])
                            ->columns(2)
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
                    ->columns([
                        'default' => 1,
                        'lg' => 2,
                        'xl' => 3,
                    ]),

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
                    ->columns([
                        'default' => 1,
                        'lg' => 2,
                        'xl' => 3,
                    ]),

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

        // Role-based table customization
        $actions = [];
        $headerActions = [];
        $bulkActions = [];

        // Only super_admin and village_admin get full actions
        if ($user && in_array($user->role, ['super_admin', 'village_admin'])) {
            $actions = [
                Tables\Actions\ActionGroup::make([
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
            ];

            $headerActions = [
                ...static::getExportHeaderActions(),
            ];

            $bulkActions = [
                Tables\Actions\BulkActionGroup::make([
                    ...static::getExportBulkActions(),
                ]),
            ];
        }

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
            ->actions($actions)
            ->headerActions($headerActions)
            ->bulkActions($bulkActions)
            ->emptyStateHeading('Belum Ada Pengaturan')
            ->emptyStateDescription('Buat pengaturan Tripay untuk desa Anda')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Buat Pengaturan')
                    ->visible(fn() => $user && in_array($user->role, ['super_admin', 'village_admin'])),
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
