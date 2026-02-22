<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationTemplateResource\Pages;
use App\Models\NotificationTemplate;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Modèles de notification';

    protected static ?string $modelLabel = 'Modèle de notification';

    protected static ?string $pluralModelLabel = 'Modèles de notification';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'task_assigned' => 'info',
                        'task_completed' => 'success',
                        'user_invitation' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'task_assigned' => 'Tâche assignée',
                        'task_completed' => 'Tâche terminée',
                        'user_invitation' => 'Invitation',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->sortable()
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Sujet')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\IconColumn::make('email_enabled')
                    ->label('Email actif')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'task_assigned' => 'Tâche assignée',
                        'task_completed' => 'Tâche terminée',
                        'user_invitation' => 'Invitation',
                    ]),
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
                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'task_assigned' => 'info',
                                'task_completed' => 'success',
                                'user_invitation' => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'task_assigned' => 'Tâche assignée',
                                'task_completed' => 'Tâche terminée',
                                'user_invitation' => 'Invitation',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('organization.name')
                            ->label('Organisation'),

                        Infolists\Components\IconEntry::make('email_enabled')
                            ->label('Email actif')
                            ->boolean(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(2),

                Infolists\Components\Section::make('Contenu')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject')
                            ->label('Sujet'),

                        Infolists\Components\TextEntry::make('body')
                            ->label('Corps du message')
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationTemplates::route('/'),
        ];
    }
}
