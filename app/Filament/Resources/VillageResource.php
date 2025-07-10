<?php
// app/Filament/Resources/VillageResource.php - Add Village management to Filament

namespace App\Filament\Resources;

use App\Filament\Resources\VillageResource\Pages;
use App\Models\Village;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VillageResource extends Resource
{
    protected static ?string $model = Village::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Villages';
    protected static ?string $modelLabel = 'Village';
    protected static ?string $pluralModelLabel = 'Villages';
    protected static ?int $navigationSort = 0;
    protected static ?string $navigationGroup = 'System';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Village Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Forms\Set $set, ?string $state) =>
                        $set('slug', Str::slug($state))),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Textarea::make('description'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('Contact Information')
                ->schema([
                    Forms\Components\TextInput::make('phone_number'),
                    Forms\Components\TextInput::make('email')->email(),
                    Forms\Components\Textarea::make('address'),
                ])
                ->columns(2),

            Forms\Components\Section::make('PAMDes Settings')
                ->schema([
                    Forms\Components\TextInput::make('pamdes_settings.default_admin_fee')
                        ->label('Default Admin Fee')
                        ->numeric()
                        ->default(5000),

                    Forms\Components\TextInput::make('pamdes_settings.default_maintenance_fee')
                        ->label('Default Maintenance Fee')
                        ->numeric()
                        ->default(2000),

                    Forms\Components\Toggle::make('pamdes_settings.auto_generate_bills')
                        ->label('Auto Generate Bills')
                        ->default(true),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\BadgeColumn::make('is_active')
                    ->boolean()
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
                Tables\Columns\TextColumn::make('customers_count')
                    ->label('Customers')
                    ->counts('customers'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVillages::route('/'),
            'create' => Pages\CreateVillage::route('/create'),
            'edit' => Pages\EditVillage::route('/{record}/edit'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['id'] = Str::uuid()->toString();
        return $data;
    }
}
