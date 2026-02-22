<?php

namespace App\Filament\Resources;

use App\Enums\InventorySessionStatus;
use App\Filament\Resources\InventorySessionResource\Pages;
use App\Models\InventorySession;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventorySessionResource extends Resource
{
    protected static ?string $model = InventorySession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Sessions d\'inventaire';

    protected static ?string $modelLabel = 'Session d\'inventaire';

    protected static ?string $pluralModelLabel = 'Sessions d\'inventaire';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->searchable()
                    ->sortable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Créé par')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('scope_type')
                    ->label('Périmètre')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tasks_count')
                    ->label('Tâches')
                    ->counts('tasks')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_expected')
                    ->label('Attendus')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_scanned')
                    ->label('Scannés')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_matched')
                    ->label('Trouvés')
                    ->numeric()
                    ->color('success')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_missing')
                    ->label('Manquants')
                    ->numeric()
                    ->color('danger')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_unexpected')
                    ->label('Inattendus')
                    ->numeric()
                    ->color('warning')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Débuté le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Terminé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options(InventorySessionStatus::class)
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
                        Infolists\Components\TextEntry::make('name')
                            ->label('Nom'),

                        Infolists\Components\TextEntry::make('organization.name')
                            ->label('Organisation'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Statut')
                            ->badge(),

                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Créé par')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('scope_type')
                            ->label('Type de périmètre')
                            ->badge()
                            ->color('gray')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])->columns(2),

                Infolists\Components\Section::make('Résultats')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_expected')
                            ->label('Assets attendus')
                            ->numeric(),

                        Infolists\Components\TextEntry::make('total_scanned')
                            ->label('Assets scannés')
                            ->numeric(),

                        Infolists\Components\TextEntry::make('total_matched')
                            ->label('Trouvés')
                            ->numeric()
                            ->color('success'),

                        Infolists\Components\TextEntry::make('total_missing')
                            ->label('Manquants')
                            ->numeric()
                            ->color('danger'),

                        Infolists\Components\TextEntry::make('total_unexpected')
                            ->label('Inattendus')
                            ->numeric()
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('tasks_count')
                            ->label('Nombre de tâches')
                            ->state(fn ($record) => $record->tasks()->count()),
                    ])->columns(3),

                Infolists\Components\Section::make('Dates')
                    ->schema([
                        Infolists\Components\TextEntry::make('started_at')
                            ->label('Débuté le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non débuté'),

                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Terminé le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non terminé'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventorySessions::route('/'),
        ];
    }
}
