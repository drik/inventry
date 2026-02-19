<?php

namespace App\Filament\App\Resources;

use App\Enums\AssetStatus;
use App\Enums\DepreciationMethod;
use App\Enums\EncodingMode;
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
                            ->icon('heroicon-o-information-circle')
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
                                                        'encoding_mode' => $tag->encoding_mode?->value,
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

                                        Forms\Components\Select::make('manufacturer_id')
                                            ->relationship('manufacturer', 'name')
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
                                                    ->maxLength(255)
                                                    ->live(debounce: 500)
                                                    ->suffixAction(
                                                        Forms\Components\Actions\Action::make('scan')
                                                            ->icon('heroicon-o-qr-code')
                                                            ->color('gray')
                                                            ->tooltip('Scan')
                                                            ->alpineClickHandler(function ($component): string {
                                                                $valuePath = $component->getStatePath();
                                                                $encodingPath = str_replace('.value', '.encoding_mode', $valuePath);

                                                                return "window.dispatchEvent(new CustomEvent('open-tag-scanner', "
                                                                    . "{ detail: { encodingMode: \$wire.get('{$encodingPath}'), "
                                                                    . "statePath: '{$valuePath}' } }))";
                                                            }),
                                                    ),

                                                Forms\Components\Select::make('encoding_mode')
                                                    ->label('Encoding')
                                                    ->options(EncodingMode::class)
                                                    ->default(fn (Forms\Get $get) => AssetTag::find($get('asset_tag_id'))?->encoding_mode)
                                                    ->required(fn (Forms\Get $get): bool => filled($get('value')))
                                                    ->nullable()
                                                    ->placeholder('None'),
                                            ])
                                            ->columns(3)
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
                                                $tagsById = $tags->keyBy('id');

                                                $existingTagIds = collect($state ?? [])
                                                    ->pluck('asset_tag_id')
                                                    ->filter()
                                                    ->toArray();

                                                $newState = $state ?? [];

                                                // Fill encoding_mode from tag default for existing entries without one
                                                foreach ($newState as &$entry) {
                                                    if (empty($entry['encoding_mode']) && isset($tagsById[$entry['asset_tag_id']])) {
                                                        $entry['encoding_mode'] = $tagsById[$entry['asset_tag_id']]->encoding_mode?->value;
                                                    }
                                                }
                                                unset($entry);

                                                // Add missing tags
                                                foreach ($tags as $tag) {
                                                    if (! in_array($tag->id, $existingTagIds)) {
                                                        $newState[] = [
                                                            'asset_tag_id' => $tag->id,
                                                            'value' => '',
                                                            'encoding_mode' => $tag->encoding_mode?->value,
                                                        ];
                                                    }
                                                }

                                                $set('tagValues', $newState);
                                            }),

                                        Forms\Components\ViewField::make('tag_scanner_modal')
                                            ->view('filament.forms.components.tag-scanner-modal')
                                            ->dehydrated(false)
                                            ->hiddenLabel(),
                                    ])
                                    ->visible(fn (Forms\Get $get) => filled($get('category_id'))),
                            ]),

                        Forms\Components\Tabs\Tab::make('Financial')
                            ->icon('heroicon-o-currency-dollar')
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
                            ->icon('heroicon-o-photo')
                            ->badge(fn (?Asset $record) => $record?->images()->count())
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

                        Forms\Components\Tabs\Tab::make('Assignments')
                            ->icon('heroicon-o-user-group')
                            ->badge(fn (?Asset $record) => $record?->assignments()->count())
                            ->schema([
                                Forms\Components\ViewField::make('assignments_display')
                                    ->view('filament.forms.components.assignments-list')
                                    ->columnSpanFull(),
                            ])
                            ->visibleOn('edit'),

                        Forms\Components\Tabs\Tab::make('Status History')
                            ->icon('heroicon-o-clock')
                            ->badge(fn (?Asset $record) => $record?->statusHistory()->count())
                            ->schema([
                                Forms\Components\ViewField::make('status_history_display')
                                    ->view('filament.forms.components.status-history-list')
                                    ->columnSpanFull(),
                            ])
                            ->visibleOn('edit'),

                        Forms\Components\Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
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
                Tables\Columns\ImageColumn::make('primaryImage.file_path')
                    ->label('Image')
                    //->square()
                    //->size(40)
                    ->width(60)
                    ->defaultImageUrl(fn ($record) => null),

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

                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Actions\CheckOutAction::make(),
                    Actions\CheckInAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ]),
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
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Infolists\Components\Section::make('Details')
                                    ->schema([
                                        Infolists\Components\Group::make([
                                            Infolists\Components\Grid::make(2)
                                                ->schema([
                                                    Infolists\Components\TextEntry::make('asset_code')
                                                        ->badge()
                                                        ->copyable(),
                                                    Infolists\Components\TextEntry::make('status')
                                                        ->badge(),
                                                ]),

                                            Infolists\Components\ImageEntry::make('primaryImage.file_path')
                                                ->label('')
                                                ->height(200),
                                        ])->columnSpan(1),

                                        Infolists\Components\ViewEntry::make('editable_details')
                                            ->hiddenLabel()
                                            ->view('filament.infolists.components.inline-edit-overview')
                                            ->columnSpan(2),
                                    ])->columns(3),

                                

                                Infolists\Components\Section::make('Identification Tags')
                                    ->schema([
                                        Infolists\Components\ViewEntry::make('tagValues')
                                            ->hiddenLabel()
                                            ->view('filament.infolists.components.tag-values')
                                            ->columns(4),
                                    ]),

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

                            ]),


                        Infolists\Components\Tabs\Tab::make('Financial')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Infolists\Components\ViewEntry::make('financial_fields')
                                    ->hiddenLabel()
                                    ->view('filament.infolists.components.inline-edit-financial')
                                    ->columnSpanFull(),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Images')
                            ->icon('heroicon-o-photo')
                            ->badge(fn ($record) => $record->images()->count())
                            ->schema([
                                Infolists\Components\ViewEntry::make('images')
                                    ->view('filament.infolists.components.image-carousel')
                                    ->columnSpanFull(),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Assignments')
                            ->icon('heroicon-o-user-group')
                            ->badge(fn ($record) => $record->assignments()->count())
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('assignments')
                                    ->hiddenLabel()
                                    ->schema([
                                        Infolists\Components\TextEntry::make('assignee.name')
                                            ->label('Assignee'),

                                        Infolists\Components\TextEntry::make('assignee_type')
                                            ->label('Type')
                                            ->badge()
                                            ->formatStateUsing(fn (string $state) => class_basename($state))
                                            ->color(fn (string $state) => match ($state) {
                                                'App\\Models\\User' => 'info',
                                                'App\\Models\\Department' => 'warning',
                                                'App\\Models\\Location' => 'success',
                                                default => 'gray',
                                            }),

                                        Infolists\Components\TextEntry::make('assigned_at')
                                            ->dateTime(),

                                        Infolists\Components\TextEntry::make('expected_return_at')
                                            ->date()
                                            ->placeholder('—')
                                            ->color(fn ($record) => $record->expected_return_at?->isPast() && ! $record->returned_at ? 'danger' : null),

                                        Infolists\Components\TextEntry::make('returned_at')
                                            ->dateTime()
                                            ->placeholder('Active')
                                            ->badge()
                                            ->color(fn ($state) => $state ? 'success' : 'warning'),

                                        Infolists\Components\TextEntry::make('assignedBy.name')
                                            ->label('Assigned By'),
                                    ])
                                    ->columns(6)
                                    ->columnSpanFull(),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Status History')
                            ->icon('heroicon-o-clock')
                            ->badge(fn ($record) => $record->statusHistory()->count())
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('statusHistory')
                                    ->hiddenLabel()
                                    ->schema([
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Date')
                                            ->dateTime(),

                                        Infolists\Components\TextEntry::make('from_status')
                                            ->badge()
                                            ->placeholder('—'),

                                        Infolists\Components\TextEntry::make('to_status')
                                            ->badge(),

                                        Infolists\Components\TextEntry::make('user.name')
                                            ->label('Changed by'),

                                        Infolists\Components\TextEntry::make('reason')
                                            ->placeholder('—'),
                                    ])
                                    ->columns(5)
                                    ->columnSpanFull(),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Infolists\Components\TextEntry::make('notes')
                                    ->hiddenLabel()
                                    ->html()
                                    ->columnSpanFull()
                                    ->placeholder('No notes.'),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
