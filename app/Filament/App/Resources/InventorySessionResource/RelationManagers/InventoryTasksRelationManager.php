<?php

namespace App\Filament\App\Resources\InventorySessionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tasks';

    public function form(Form $form): Form
    {
        return $form
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

                Forms\Components\Textarea::make('notes'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->withCount(['notes', 'media']))
            ->columns([
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Assignee'),

                Tables\Columns\TextColumn::make('location.name')
                    ->placeholder('All'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('notes_count')
                    ->label('Notes')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'info' : 'gray')
                    ->icon('heroicon-o-pencil-square')
                    ->placeholder('0'),

                Tables\Columns\TextColumn::make('media_count')
                    ->label('Médias')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray')
                    ->icon('heroicon-o-paper-clip')
                    ->placeholder('0'),

                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('Détails')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn ($record) => 'Tâche — ' . ($record->assignee?->name ?? 'N/A'))
                    ->modalContent(function ($record) {
                        // Load via relationship methods to avoid conflict with the 'notes' text column
                        return view('filament.app.resources.inventory-session-resource.partials.task-notes-media', [
                            'task' => $record,
                            'taskNotes' => $record->notes()->with(['creator', 'sourceMedia'])->latest()->get(),
                            'taskMediaItems' => $record->media()->with('uploader')->latest()->get(),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->slideOver(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
