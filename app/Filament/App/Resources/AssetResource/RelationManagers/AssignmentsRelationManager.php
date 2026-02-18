<?php

namespace App\Filament\App\Resources\AssetResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Assignee'),

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
                    ->color(fn ($record) => $record->expected_return_at?->isPast() && ! $record->returned_at ? 'danger' : null),

                Tables\Columns\TextColumn::make('returned_at')
                    ->dateTime()
                    ->placeholder('Active'),

                Tables\Columns\TextColumn::make('assignedBy.name')
                    ->label('Assigned By'),
            ])
            ->defaultSort('assigned_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
