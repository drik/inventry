<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AssignmentResource\Pages;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssignmentResource extends Resource
{
    protected static ?string $model = Assignment::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';

    protected static ?string $navigationGroup = 'Assignments';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Assignment')
                    ->schema([
                        Forms\Components\Select::make('asset_id')
                            ->relationship('asset', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('assignee_type')
                            ->options([
                                'App\\Models\\User' => 'User',
                                'App\\Models\\Department' => 'Department',
                                'App\\Models\\Location' => 'Location',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('assignee_id', null)),

                        Forms\Components\Select::make('assignee_id')
                            ->label('Assignee')
                            ->required()
                            ->searchable()
                            ->options(function (Get $get) {
                                return match ($get('assignee_type')) {
                                    'App\\Models\\User' => User::pluck('name', 'id'),
                                    'App\\Models\\Department' => Department::pluck('name', 'id'),
                                    'App\\Models\\Location' => Location::pluck('name', 'id'),
                                    default => [],
                                };
                            }),

                        Forms\Components\DateTimePicker::make('assigned_at')
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('expected_return_at'),

                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('signature_path')
                            ->label('Signature')
                            ->directory('signatures')
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('asset.name')
                    ->description(fn ($record) => $record->asset?->asset_code)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->searchable(),

                Tables\Columns\TextColumn::make('assignee_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => class_basename($state))
                    ->color(fn (string $state) => match ($state) {
                        'App\\Models\\User' => 'info',
                        'App\\Models\\Department' => 'warning',
                        'App\\Models\\Location' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('assigned_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_return_at')
                    ->date()
                    ->color(fn ($record) => $record->expected_return_at?->isPast() && ! $record->returned_at ? 'danger' : null)
                    ->sortable(),

                Tables\Columns\TextColumn::make('returned_at')
                    ->dateTime()
                    ->placeholder('Active')
                    ->sortable(),

                Tables\Columns\TextColumn::make('assignedBy.name')
                    ->label('Assigned By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('assigned_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->queries(
                        true: fn ($query) => $query->whereNull('returned_at'),
                        false: fn ($query) => $query->whereNotNull('returned_at'),
                    ),

                Tables\Filters\SelectFilter::make('assignee_type')
                    ->options([
                        'App\\Models\\User' => 'User',
                        'App\\Models\\Department' => 'Department',
                        'App\\Models\\Location' => 'Location',
                    ]),

                Tables\Filters\Filter::make('overdue')
                    ->query(fn ($query) => $query
                        ->whereNotNull('expected_return_at')
                        ->where('expected_return_at', '<', now())
                        ->whereNull('returned_at')
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->returned_at === null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssignments::route('/'),
            'create' => Pages\CreateAssignment::route('/create'),
            'view' => Pages\ViewAssignment::route('/{record}'),
        ];
    }
}
