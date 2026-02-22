<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Laravel\Paddle\Subscription;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Abonnements';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Souscriptions';

    protected static ?string $modelLabel = 'Souscription';

    protected static ?string $pluralModelLabel = 'Souscriptions';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('billable.name')
                    ->label('Organisation')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trialing' => 'info',
                        'past_due' => 'warning',
                        'paused' => 'gray',
                        'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Actif',
                        'trialing' => 'Essai',
                        'past_due' => 'En retard',
                        'paused' => 'En pause',
                        'canceled' => 'Annulé',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('paddle_id')
                    ->label('Paddle ID')
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->label('Fin d\'essai')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('paused_at')
                    ->label('Mis en pause le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Fin le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active' => 'Actif',
                        'trialing' => 'Essai',
                        'past_due' => 'En retard',
                        'paused' => 'En pause',
                        'canceled' => 'Annulé',
                    ])
                    ->multiple(),
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
                        Infolists\Components\TextEntry::make('billable.name')
                            ->label('Organisation'),

                        Infolists\Components\TextEntry::make('type'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'trialing' => 'info',
                                'past_due' => 'warning',
                                'paused' => 'gray',
                                'canceled' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'active' => 'Actif',
                                'trialing' => 'Essai',
                                'past_due' => 'En retard',
                                'paused' => 'En pause',
                                'canceled' => 'Annulé',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('paddle_id')
                            ->label('Paddle ID')
                            ->copyable(),
                    ])->columns(2),

                Infolists\Components\Section::make('Dates')
                    ->schema([
                        Infolists\Components\TextEntry::make('trial_ends_at')
                            ->label('Fin de la période d\'essai')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('paused_at')
                            ->label('Mis en pause le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('ends_at')
                            ->label('Fin de l\'abonnement')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(2),

                Infolists\Components\Section::make('Articles')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('product_id')
                                    ->label('Produit ID'),

                                Infolists\Components\TextEntry::make('price_id')
                                    ->label('Prix ID'),

                                Infolists\Components\TextEntry::make('status')
                                    ->label('Statut')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Quantité'),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
        ];
    }
}
