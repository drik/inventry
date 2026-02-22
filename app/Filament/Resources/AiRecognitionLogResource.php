<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiRecognitionLogResource\Pages;
use App\Models\AiRecognitionLog;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AiRecognitionLogResource extends Resource
{
    protected static ?string $model = AiRecognitionLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Logs IA';

    protected static ?string $modelLabel = 'Log IA';

    protected static ?string $pluralModelLabel = 'Logs IA';

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
                    ->limit(20),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable()
                    ->limit(15),

                Tables\Columns\TextColumn::make('use_case')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'identify' => 'primary',
                        'verify' => 'info',
                        'match' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'identify' => 'Identification',
                        'verify' => 'Vérification',
                        'match' => 'Correspondance',
                        default => $state,
                    })
                    ->sortable(),

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
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('model')
                    ->label('Modèle')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('estimated_cost_usd')
                    ->label('Coût')
                    ->money('usd', 4)
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('prompt_tokens')
                    ->label('Tokens (prompt)')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('completion_tokens')
                    ->label('Tokens (complétion)')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('use_case')
                    ->label('Type')
                    ->options([
                        'identify' => 'Identification',
                        'verify' => 'Vérification',
                        'match' => 'Correspondance',
                    ]),

                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        'gemini' => 'Gemini',
                        'openai' => 'GPT-4o',
                    ]),

                Tables\Filters\SelectFilter::make('selected_action')
                    ->label('Résultat')
                    ->options([
                        'matched' => 'Confirmé',
                        'unexpected' => 'Inattendu',
                        'dismissed' => 'Rejeté',
                    ]),

                Tables\Filters\TernaryFilter::make('used_fallback')
                    ->label('Fallback utilisé'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Requête')
                    ->schema([
                        Infolists\Components\TextEntry::make('organization.name')
                            ->label('Organisation'),

                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Utilisateur'),

                        Infolists\Components\TextEntry::make('use_case')
                            ->label('Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'identify' => 'primary',
                                'verify' => 'info',
                                'match' => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'identify' => 'Identification',
                                'verify' => 'Vérification',
                                'match' => 'Correspondance',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Date')
                            ->dateTime('d/m/Y H:i:s'),

                        Infolists\Components\TextEntry::make('captured_image_path')
                            ->label('Image capturée')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('task.id')
                            ->label('Tâche')
                            ->placeholder('—'),
                    ])->columns(2),

                Infolists\Components\Section::make('Provider IA')
                    ->schema([
                        Infolists\Components\TextEntry::make('provider')
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

                        Infolists\Components\TextEntry::make('model')
                            ->label('Modèle'),

                        Infolists\Components\IconEntry::make('used_fallback')
                            ->label('Fallback utilisé')
                            ->boolean(),

                        Infolists\Components\TextEntry::make('latency_ms')
                            ->label('Latence')
                            ->suffix(' ms')
                            ->color(fn (?int $state): string => match (true) {
                                $state === null => 'gray',
                                $state < 3000 => 'success',
                                $state < 6000 => 'warning',
                                default => 'danger',
                            }),
                    ])->columns(2),

                Infolists\Components\Section::make('Tokens & Coût')
                    ->schema([
                        Infolists\Components\TextEntry::make('prompt_tokens')
                            ->label('Tokens (prompt)')
                            ->numeric()
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('completion_tokens')
                            ->label('Tokens (complétion)')
                            ->numeric()
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('total_tokens')
                            ->label('Total tokens')
                            ->state(fn ($record) => ($record->prompt_tokens ?? 0) + ($record->completion_tokens ?? 0))
                            ->numeric(),

                        Infolists\Components\TextEntry::make('estimated_cost_usd')
                            ->label('Coût estimé')
                            ->money('usd', 6),
                    ])->columns(4),

                Infolists\Components\Section::make('Résultat')
                    ->schema([
                        Infolists\Components\TextEntry::make('selected_action')
                            ->label('Action')
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

                        Infolists\Components\TextEntry::make('selectedAsset.name')
                            ->label('Asset sélectionné')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('matched_asset_ids')
                            ->label('Assets correspondants')
                            ->state(fn ($record) => $record->matched_asset_ids ? implode(', ', $record->matched_asset_ids) : null)
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])->columns(2),

                Infolists\Components\Section::make('Réponse IA brute')
                    ->schema([
                        Infolists\Components\TextEntry::make('ai_response')
                            ->label('')
                            ->state(fn ($record) => $record->ai_response ? json_encode($record->ai_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                            ->markdown()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiRecognitionLogs::route('/'),
        ];
    }
}
