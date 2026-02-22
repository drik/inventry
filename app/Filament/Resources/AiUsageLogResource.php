<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiUsageLogResource\Pages;
use App\Models\AiUsageLog;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AiUsageLogResource extends Resource
{
    protected static ?string $model = AiUsageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Logs Usage IA';

    protected static ?string $modelLabel = 'Log Usage IA';

    protected static ?string $pluralModelLabel = 'Logs Usage IA';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('feature')
                    ->label('Fonctionnalité')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ai_identify' => 'primary',
                        'ai_verify' => 'info',
                        'ai_match' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('tokens_used')
                    ->label('Tokens utilisés')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('feature')
                    ->label('Fonctionnalité')
                    ->options(
                        AiUsageLog::query()
                            ->distinct()
                            ->pluck('feature', 'feature')
                            ->toArray()
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informations')
                    ->schema([
                        Infolists\Components\TextEntry::make('organization.name')
                            ->label('Organisation'),

                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Utilisateur'),

                        Infolists\Components\TextEntry::make('feature')
                            ->label('Fonctionnalité')
                            ->badge(),

                        Infolists\Components\TextEntry::make('tokens_used')
                            ->label('Tokens utilisés')
                            ->numeric(),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Date')
                            ->dateTime('d/m/Y H:i:s'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiUsageLogs::route('/'),
        ];
    }
}
