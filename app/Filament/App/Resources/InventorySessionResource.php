<?php

namespace App\Filament\App\Resources;

use App\Enums\InventorySessionStatus;
use App\Filament\App\Resources\InventorySessionResource\Pages;
use App\Filament\App\Resources\InventorySessionResource\RelationManagers;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\InventorySession;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventorySessionResource extends Resource
{
    protected static ?string $model = InventorySession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Session')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('scope_type')
                            ->options([
                                'all' => 'All Assets',
                                'location' => 'By Location',
                                'category' => 'By Category',
                                'department' => 'By Department',
                            ])
                            ->default('all')
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('scope_ids')
                            ->label('Scope')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => $get('scope_type') !== 'all')
                            ->options(function (Get $get) {
                                return match ($get('scope_type')) {
                                    'location' => Location::pluck('name', 'id'),
                                    'category' => AssetCategory::pluck('name', 'id'),
                                    'department' => Department::pluck('name', 'id'),
                                    default => [],
                                };
                            }),
                    ])->columns(2),

                Forms\Components\Section::make('Team')
                    ->schema([
                        Forms\Components\Repeater::make('tasks')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('assigned_to')
                                    ->label('Assignee')
                                    ->relationship('assignee', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Select::make('location_id')
                                    ->relationship('location', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Team Member'),
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

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('scope_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('total_expected')
                    ->label('Expected')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_scanned')
                    ->label('Scanned')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_matched')
                    ->label('Matched')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_missing')
                    ->label('Missing')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : null)
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(InventorySessionStatus::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status === InventorySessionStatus::Draft),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InventoryTasksRelationManager::class,
            RelationManagers\InventoryItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventorySessions::route('/'),
            'create' => Pages\CreateInventorySession::route('/create'),
            'view' => Pages\ViewInventorySession::route('/{record}'),
            'edit' => Pages\EditInventorySession::route('/{record}/edit'),
            'execute' => Pages\ExecuteInventorySession::route('/{record}/execute'),
            'execute-task' => Pages\ExecuteInventoryTask::route('/{record}/execute-task/{taskId}'),
            'execute-task-mobile' => Pages\ExecuteInventoryTaskMobile::route('/{record}/execute-task-mobile/{taskId}'),
        ];
    }
}
