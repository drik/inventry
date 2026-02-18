<?php

namespace App\Filament\App\Resources;

use App\Enums\AssetStatus;
use App\Enums\DepreciationMethod;
use App\Filament\App\Resources\AssetResource\Actions;
use App\Filament\App\Resources\AssetResource\Pages;
use App\Filament\App\Resources\AssetResource\RelationManagers;
use App\Models\Asset;
use App\Models\AssetTag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Asset Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Asset')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('General')
                            ->schema([
                                Forms\Components\Section::make('Basic Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\Select::make('category_id')
                                            ->relationship('category', 'name')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function (?string $state, Forms\Set $set) {
                                                if (! $state) {
                                                    $set('tagValues', []);

                                                    return;
                                                }

                                                $tags = AssetTag::where('category_id', $state)
                                                    ->orderBy('sort_order')
                                                    ->get();

                                                $values = [];
                                                foreach ($tags as $tag) {
                                                    $values[] = [
                                                        'asset_tag_id' => $tag->id,
                                                        'value' => '',
                                                    ];
                                                }

                                                $set('tagValues', $values);
                                            }),

                                        Forms\Components\Select::make('location_id')
                                            ->relationship('location', 'name')
                                            ->required()
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('department_id')
                                            ->relationship('department', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

                                        Forms\Components\Select::make('status')
                                            ->options(AssetStatus::class)
                                            ->default(AssetStatus::Available)
                                            ->required()
                                            ->visibleOn('edit'),
                                    ])->columns(2),

                                Forms\Components\Section::make('Identification')
                                    ->schema([
                                        Forms\Components\TextInput::make('asset_code')
                                            ->label('Asset Code')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Auto-generated')
                                            ->visibleOn('edit'),

                                        Forms\Components\TextInput::make('serial_number')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('sku')
                                            ->label('SKU')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('barcode')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Auto-generated')
                                            ->visibleOn('edit'),
                                    ])->columns(2),

                                Forms\Components\Section::make('Identification Tags')
                                    ->schema([
                                        Forms\Components\Repeater::make('tagValues')
                                            ->relationship()
                                            ->schema([
                                                Forms\Components\Select::make('asset_tag_id')
                                                    ->label('Tag')
                                                    ->options(function (Forms\Get $get) {
                                                        $categoryId = $get('../../category_id');
                                                        if (! $categoryId) {
                                                            return [];
                                                        }

                                                        return AssetTag::where('category_id', $categoryId)
                                                            ->orderBy('sort_order')
                                                            ->pluck('name', 'id');
                                                    })
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->required(),

                                                Forms\Components\TextInput::make('value')
                                                    ->required(fn (Forms\Get $get): bool => AssetTag::find($get('asset_tag_id'))?->is_required ?? false)
                                                    ->helperText(fn (Forms\Get $get): ?string => AssetTag::find($get('asset_tag_id'))?->description)
                                                    ->maxLength(255),
                                            ])
                                            ->columns(2)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->defaultItems(0)
                                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): ?array {
                                                if (empty($data['value'])) {
                                                    return null;
                                                }

                                                return $data;
                                            })
                                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): ?array {
                                                if (empty($data['value'])) {
                                                    return null;
                                                }

                                                return $data;
                                            })
                                            ->afterStateHydrated(function ($state, Forms\Get $get, Forms\Set $set) {
                                                $categoryId = $get('category_id');
                                                if (! $categoryId) {
                                                    return;
                                                }

                                                $tags = AssetTag::where('category_id', $categoryId)
                                                    ->orderBy('sort_order')
                                                    ->get();

                                                $existingTagIds = collect($state ?? [])
                                                    ->pluck('asset_tag_id')
                                                    ->filter()
                                                    ->toArray();

                                                $newState = $state ?? [];
                                                foreach ($tags as $tag) {
                                                    if (! in_array($tag->id, $existingTagIds)) {
                                                        $newState[] = [
                                                            'asset_tag_id' => $tag->id,
                                                            'value' => '',
                                                        ];
                                                    }
                                                }

                                                $set('tagValues', $newState);
                                            }),
                                    ])
                                    ->visible(fn (Forms\Get $get) => filled($get('category_id'))),
                            ]),

                        Forms\Components\Tabs\Tab::make('Financial')
                            ->schema([
                                Forms\Components\Section::make('Purchase')
                                    ->schema([
                                        Forms\Components\DatePicker::make('purchase_date'),

                                        Forms\Components\TextInput::make('purchase_cost')
                                            ->numeric()
                                            ->prefix('$'),
                                    ])->columns(2),

                                Forms\Components\Section::make('Warranty')
                                    ->schema([
                                        Forms\Components\DatePicker::make('warranty_expiry'),
                                    ]),

                                Forms\Components\Section::make('Depreciation')
                                    ->schema([
                                        Forms\Components\Select::make('depreciation_method')
                                            ->options(DepreciationMethod::class),

                                        Forms\Components\TextInput::make('useful_life_months')
                                            ->label('Useful Life (months)')
                                            ->numeric(),

                                        Forms\Components\TextInput::make('salvage_value')
                                            ->numeric()
                                            ->prefix('$'),
                                    ])->columns(3),
                            ]),

                        Forms\Components\Tabs\Tab::make('Images')
                            ->schema([
                                Forms\Components\Repeater::make('images')
                                    ->relationship()
                                    ->schema([
                                        Forms\Components\FileUpload::make('file_path')
                                            ->label('Image')
                                            ->image()
                                            ->directory('asset-images')
                                            ->required(),

                                        Forms\Components\TextInput::make('caption')
                                            ->maxLength(255),

                                        Forms\Components\Toggle::make('is_primary')
                                            ->label('Primary'),
                                    ])
                                    ->grid(4)
                                    ->defaultItems(0)
                                    ->reorderable()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['caption'] ?? 'Image'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Notes')
                            ->schema([
                                Forms\Components\RichEditor::make('notes')
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('asset_code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('category.name')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('location.name')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('currentAssignment.assignee.name')
                    ->label('Assigned To')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('serial_number')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('warranty_expiry')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->warranty_expiry?->isPast() ? 'danger' : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(AssetStatus::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category')
                    ->preload(),

                Tables\Filters\SelectFilter::make('location_id')
                    ->relationship('location', 'name')
                    ->label('Location')
                    ->preload(),

                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Department')
                    ->preload(),

                Tables\Filters\TernaryFilter::make('has_warranty')
                    ->label('Has Warranty')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('warranty_expiry'),
                        false: fn ($query) => $query->whereNull('warranty_expiry'),
                    ),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Actions\CheckOutAction::make(),
                Actions\CheckInAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Asset')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Overview')
                            ->schema([
                                Infolists\Components\Section::make('Details')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('asset_code')
                                            ->badge()
                                            ->copyable(),

                                        Infolists\Components\TextEntry::make('name'),

                                        Infolists\Components\TextEntry::make('category.name'),

                                        Infolists\Components\TextEntry::make('status')
                                            ->badge(),

                                        Infolists\Components\TextEntry::make('location.name'),

                                        Infolists\Components\TextEntry::make('department.name')
                                            ->placeholder('—'),

                                        Infolists\Components\TextEntry::make('serial_number')
                                            ->copyable()
                                            ->placeholder('—'),

                                        Infolists\Components\TextEntry::make('barcode')
                                            ->copyable(),
                                    ])->columns(2),

                                Infolists\Components\Section::make('Current Assignment')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('currentAssignment.assignee.name')
                                            ->label('Assigned To')
                                            ->placeholder('Not assigned'),

                                        Infolists\Components\TextEntry::make('currentAssignment.assigned_at')
                                            ->label('Assigned At')
                                            ->dateTime()
                                            ->placeholder('—'),

                                        Infolists\Components\TextEntry::make('currentAssignment.expected_return_at')
                                            ->label('Expected Return')
                                            ->date()
                                            ->placeholder('—'),
                                    ])->columns(3),

                                Infolists\Components\Section::make('Identification Tags')
                                    ->schema(function ($record) {
                                        if (! $record || $record->tagValues->isEmpty()) {
                                            return [
                                                Infolists\Components\TextEntry::make('no_tags')
                                                    ->hiddenLabel()
                                                    ->default('No identification tags.')
                                                    ->color('gray'),
                                            ];
                                        }

                                        return $record->tagValues->map(function ($tagValue) {
                                            return Infolists\Components\TextEntry::make("tagValue_{$tagValue->id}")
                                                ->label($tagValue->tag?->name ?? 'Tag')
                                                ->default($tagValue->value)
                                                ->copyable()
                                                ->badge();
                                        })->toArray();
                                    })
                                    ->columns(2),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Financial')
                            ->schema([
                                Infolists\Components\TextEntry::make('purchase_cost')
                                    ->money('USD'),

                                Infolists\Components\TextEntry::make('purchase_date')
                                    ->date(),

                                Infolists\Components\TextEntry::make('warranty_expiry')
                                    ->date()
                                    ->color(fn ($record) => $record->warranty_expiry?->isPast() ? 'danger' : null),

                                Infolists\Components\TextEntry::make('depreciation_method')
                                    ->placeholder('—'),

                                Infolists\Components\TextEntry::make('useful_life_months')
                                    ->suffix(' months')
                                    ->placeholder('—'),

                                Infolists\Components\TextEntry::make('salvage_value')
                                    ->money('USD')
                                    ->placeholder('—'),
                            ])->columns(2),

                        Infolists\Components\Tabs\Tab::make('Images')
                            ->schema([
                                Infolists\Components\ViewEntry::make('images')
                                    ->view('filament.infolists.components.image-carousel')
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AssignmentsRelationManager::class,
            RelationManagers\ImagesRelationManager::class,
            RelationManagers\StatusHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'view' => Pages\ViewAsset::route('/{record}'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['asset_code', 'name', 'serial_number', 'sku', 'barcode'];
    }
}
