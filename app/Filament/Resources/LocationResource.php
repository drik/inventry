<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Localisations';

    protected static ?string $modelLabel = 'Localisation';

    protected static ?string $pluralModelLabel = 'Localisations';

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
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->sortable()
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('address')
                    ->label('Adresse')
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('Ville')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('Pays')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('departments_count')
                    ->label('Départements')
                    ->counts('departments')
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Contact')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),
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

                        Infolists\Components\TextEntry::make('parent.name')
                            ->label('Localisation parente')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('address')
                            ->label('Adresse')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('city')
                            ->label('Ville')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('country')
                            ->label('Pays')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('contact_person')
                            ->label('Personne de contact')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('contact_phone')
                            ->label('Téléphone de contact')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('departments_count')
                            ->label('Nombre de départements')
                            ->state(fn ($record) => $record->departments()->count()),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
