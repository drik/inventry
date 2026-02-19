<?php

namespace App\Filament\Widgets\Admin;

use App\Models\Organization;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentOrganizationsTable extends TableWidget
{
    protected static ?string $heading = 'Dernières organisations inscrites';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Organization::query()
                    ->withCount(['assets', 'users'])
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Organisation')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Propriétaire'),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Utilisateurs')
                    ->counts('users')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('assets_count')
                    ->label('Assets')
                    ->counts('assets')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10]);
    }
}
