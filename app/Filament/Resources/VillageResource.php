<?php
// app/Filament/Resources/VillageResource.php - Fixed Village management for Filament

namespace App\Filament\Resources;

use App\Filament\Resources\VillageResource\Pages;
use App\Models\Village;
use App\Traits\ExportableResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VillageResource extends Resource
{
    use ExportableResource; // Add this trait

    protected static ?string $model = Village::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Desa';
    protected static ?string $modelLabel = 'Desa';
    protected static ?string $pluralModelLabel = 'Desa';
    protected static ?int $navigationSort = 0;
    protected static ?string $navigationGroup = 'System';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Desa')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Desa')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Forms\Set $set, ?string $state) =>
                        $set('slug', Str::slug($state))),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Textarea::make('description')
                        ->label('Deskripsi'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('Informasi Kontak')
                ->schema([
                    Forms\Components\TextInput::make('phone_number')
                        ->label('Nomor Telepon'),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email(),
                    Forms\Components\Textarea::make('address')
                        ->label('Alamat'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Pengaturan PAMDes')
                ->schema([
                    Forms\Components\TextInput::make('pamdes_settings.default_admin_fee')
                        ->label('Biaya Admin Default')
                        ->numeric()
                        ->default(5000)
                        ->prefix('Rp'),

                    Forms\Components\TextInput::make('pamdes_settings.default_maintenance_fee')
                        ->label('Biaya Pemeliharaan Default')
                        ->numeric()
                        ->default(2000)
                        ->prefix('Rp'),

                    Forms\Components\Toggle::make('pamdes_settings.auto_generate_bills')
                        ->label('Auto Generate Tagihan')
                        ->default(true),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Desa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('customers_count')
                    ->label('Jumlah Pelanggan')
                    ->counts('customers'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),

                static::getStatusFilter([
                    true => 'Aktif',
                    false => 'Tidak Aktif',
                ]),

                static::getDateRangeFilter('Tanggal Didirikan', 'established_at'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ...static::getExportBulkActions(),
                ]),
            ])->headerActions([
                ...static::getExportHeaderActions(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVillages::route('/'),
            'create' => Pages\CreateVillage::route('/create'),
            'view' => Pages\ViewVillage::route('/{record}'),
            'edit' => Pages\EditVillage::route('/{record}/edit'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['id'] = Str::uuid()->toString();
        $data['established_at'] = now();
        return $data;
    }
}
