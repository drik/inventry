<?php

namespace App\Filament\Widgets\Admin;

use App\Models\AiRecognitionLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AiRecentLogsTable extends TableWidget
{
    protected static ?string $heading = 'Dernières requêtes IA';

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AiRecognitionLog::query()
                    ->with(['organization', 'user'])
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->limit(20)
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->limit(15),
                Tables\Columns\TextColumn::make('use_case')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'identify' => 'primary',
                        'verify' => 'info',
                        'match' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('provider')
                    ->label('Provider')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'gemini' => 'info',
                        'openai' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'gemini' => 'Gemini',
                        'openai' => 'GPT-4o',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('used_fallback')
                    ->label('Fallback')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-path')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('selected_action')
                    ->label('Résultat')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'matched' => 'success',
                        'unexpected' => 'warning',
                        'dismissed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'matched' => 'Confirmé',
                        'unexpected' => 'Inattendu',
                        'dismissed' => 'Rejeté',
                        default => 'En attente',
                    }),
                Tables\Columns\TextColumn::make('latency_ms')
                    ->label('Latence')
                    ->suffix(' ms')
                    ->alignEnd()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state < 3000 => 'success',
                        $state < 6000 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('estimated_cost_usd')
                    ->label('Coût')
                    ->money('usd', 4)
                    ->alignEnd(),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([5, 10, 25]);
    }
}
