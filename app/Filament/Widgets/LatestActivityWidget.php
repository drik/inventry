<?php

namespace App\Filament\Widgets;

use App\Models\AssetStatusHistory;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestActivityWidget extends TableWidget
{
    protected static ?string $heading = 'Activité récente';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AssetStatusHistory::query()
                    ->with(['asset', 'user'])
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Asset')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('from_status')
                    ->label('Ancien statut')
                    ->badge(),
                Tables\Columns\TextColumn::make('to_status')
                    ->label('Nouveau statut')
                    ->badge(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Par')
                    ->default('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->since(),
            ])
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10]);
    }
}
