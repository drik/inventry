<?php

namespace App\Filament\App\Resources;

use App\Enums\DepreciationMethod;
use App\Enums\EncodingMode;
use App\Filament\App\Resources\AssetCategoryResource\Pages;
use App\Filament\App\Resources\AssetCategoryResource\RelationManagers;
use App\Models\AssetCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AssetCategoryResource extends Resource
{
    protected static ?string $model = AssetCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Asset Management';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Category Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->rows(3),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Category')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\TextInput::make('icon')
                            ->nullable()
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Depreciation Defaults')
                    ->schema([
                        Forms\Components\Select::make('depreciation_method')
                            ->options(DepreciationMethod::class)
                            ->nullable(),

                        Forms\Components\TextInput::make('default_useful_life_months')
                            ->label('Default Useful Life (months)')
                            ->numeric()
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('Identification Tags')
                    ->description('Define identification tags for assets in this category (QR codes, NFC, RFID, etc.)')
                    ->schema([
                        Forms\Components\Repeater::make('identificationTags')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Numéro PAK'),

                                Forms\Components\Textarea::make('description')
                                    ->rows(2)
                                    ->placeholder('e.g. Numéro Produit Avariable du Québec'),

                                Forms\Components\Toggle::make('is_required')
                                    ->label('Required'),

                                Forms\Components\Select::make('encoding_mode')
                                    ->label('Encoding Mode')
                                    ->options(EncodingMode::class)
                                    ->nullable()
                                    ->placeholder('None'),

                                Forms\Components\TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Tag')
                            ->addActionLabel('Add Identification Tag'),
                    ]),

                Forms\Components\Section::make('Custom Fields')
                    ->schema([
                        Forms\Components\Repeater::make('customFields')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('field_key', Str::slug($state, '_'))),

                                Forms\Components\TextInput::make('field_key')
                                    ->required()
                                    ->rules(['alpha_dash']),

                                Forms\Components\Select::make('field_type')
                                    ->required()
                                    ->options([
                                        'text' => 'Text',
                                        'number' => 'Number',
                                        'date' => 'Date',
                                        'select' => 'Select',
                                        'boolean' => 'Boolean',
                                        'url' => 'URL',
                                    ])
                                    ->reactive(),

                                Forms\Components\TagsInput::make('options')
                                    ->visible(fn (Forms\Get $get) => $get('field_type') === 'select'),

                                Forms\Components\Toggle::make('is_required'),

                                Forms\Components\TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('identification_tags_count')
                    ->label('ID Tags')
                    ->counts('identificationTags')
                    ->sortable(),

                Tables\Columns\TextColumn::make('custom_fields_count')
                    ->label('Custom Fields')
                    ->counts('customFields')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CustomFieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetCategories::route('/'),
            'create' => Pages\CreateAssetCategory::route('/create'),
            'edit' => Pages\EditAssetCategory::route('/{record}/edit'),
        ];
    }
}
