<?php
// app/Filament/Resources/CustomerResource.php - Updated without export filters

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Models\User;
use App\Models\Village;
use App\Traits\ExportableResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CustomerResource extends Resource
{
    use ExportableResource; // Simplified trait without filters

    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Pelanggan';
    protected static ?string $modelLabel = 'Pelanggan';
    protected static ?string $pluralModelLabel = 'Pelanggan';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationGroup = 'Manajemen Data';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('village');

        // For super admin, show all customers or filter by current village context
        $user = User::find(Auth::user()->id);
        if ($user?->isSuperAdmin()) {
            $currentVillage = $user->getCurrentVillageContext();
            if ($currentVillage) {
                $query->byVillage($currentVillage);
            }
        } else {
            // For village admin, only show customers from their accessible villages
            $accessibleVillages = $user?->getAccessibleVillages()->pluck('id') ?? collect();
            $query->whereIn('village_id', $accessibleVillages);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $user = Auth::user();
        $user = User::find($user->id);
        $currentVillageId = $user?->getCurrentVillageContext();

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pelanggan')
                    ->schema([
                        Forms\Components\Select::make('village_id')
                            ->label('Desa')
                            ->relationship('village', 'name')
                            ->default($currentVillageId)
                            ->disabled() // Read-only to prevent moving customers between villages
                            ->dehydrated()
                            ->required()
                            ->visible(fn() => $user?->isSuperAdmin()),

                        Forms\Components\Placeholder::make('village_display')
                            ->label('Desa')
                            ->content(function (?Customer $record) use ($currentVillageId) {
                                if ($record && $record->village) {
                                    return $record->village;
                                }
                                if ($currentVillageId) {
                                    $village = Village::find($currentVillageId);
                                    return $village?->name ?? 'Unknown Village';
                                }
                                return 'No Village Selected';
                            })
                            ->visible(fn() => !$user?->isSuperAdmin()),

                        Forms\Components\Hidden::make('village_id')
                            ->default($currentVillageId)
                            ->visible(fn() => !$user?->isSuperAdmin()),

                        Forms\Components\TextInput::make('customer_code')
                            ->label('Kode Pelanggan')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn() => Customer::generateCustomerCode($currentVillageId))
                            ->maxLength(20),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone_number')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Aktif',
                                'inactive' => 'Tidak Aktif',
                            ])
                            ->searchable()
                            ->default('active')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Alamat')
                    ->schema([
                        Forms\Components\Textarea::make('address')
                            ->label('Alamat Lengkap')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('rt')
                            ->label('RT')
                            ->maxLength(5),

                        Forms\Components\TextInput::make('rw')
                            ->label('RW')
                            ->maxLength(5),

                        Forms\Components\TextInput::make('village')
                            ->label('Desa/Kelurahan')
                            ->maxLength(100),
                    ])
                    ->columns(3),
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
                    ->visible($isSuperAdmin)
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('customer_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Telepon')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('village_id')
                    ->label('Desa')
                    ->relationship('village', 'name')
                    ->visible($isSuperAdmin),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    ])
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ])
            ])
            ->headerActions([
                // Simplified Export Actions - exports all data without processing table filters
                ...static::getExportHeaderActions(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Simplified Bulk Export Actions - no filter processing
                    ...static::getExportBulkActions(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
