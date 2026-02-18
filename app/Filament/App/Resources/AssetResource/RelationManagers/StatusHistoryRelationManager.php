<?php

namespace App\Filament\App\Resources\AssetResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';

    protected static ?string $title = 'Status History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('from_status')
                    ->badge()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('to_status')
                    ->badge(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Changed by'),

                Tables\Columns\TextColumn::make('reason')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
