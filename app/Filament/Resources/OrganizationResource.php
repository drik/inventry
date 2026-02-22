<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Organisations';

    protected static ?string $modelLabel = 'Organisation';

    protected static ?string $pluralModelLabel = 'Organisations';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->circular()
                    ->defaultImageUrl(fn () => null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Propriétaire')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Premium' => 'warning',
                        'Pro' => 'info',
                        'Basic' => 'success',
                        'Freemium' => 'gray',
                        default => 'gray',
                    })
                    ->placeholder('Aucun'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Utilisateurs')
                    ->counts('users')
                    ->sortable(),

                Tables\Columns\TextColumn::make('assets_count')
                    ->label('Assets')
                    ->counts('assets')
                    ->sortable(),

                Tables\Columns\TextColumn::make('locations_count')
                    ->label('Localisations')
                    ->counts('locations')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Téléphone')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'name')
                    ->preload(),

                Tables\Filters\TrashedFilter::make(),
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
                        Infolists\Components\ImageEntry::make('logo_path')
                            ->label('Logo')
                            ->circular()
                            ->defaultImageUrl(fn () => null),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Nom'),

                        Infolists\Components\TextEntry::make('slug'),

                        Infolists\Components\TextEntry::make('owner.name')
                            ->label('Propriétaire')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('plan.name')
                            ->label('Plan')
                            ->badge()
                            ->placeholder('Aucun'),

                        Infolists\Components\TextEntry::make('address')
                            ->label('Adresse')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('phone')
                            ->label('Téléphone')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(2),

                Infolists\Components\Section::make('Statistiques')
                    ->schema([
                        Infolists\Components\TextEntry::make('users_count')
                            ->label('Utilisateurs')
                            ->state(fn ($record) => $record->users()->count()),

                        Infolists\Components\TextEntry::make('assets_count')
                            ->label('Assets')
                            ->state(fn ($record) => $record->assets()->count()),

                        Infolists\Components\TextEntry::make('locations_count')
                            ->label('Localisations')
                            ->state(fn ($record) => $record->locations()->count()),

                        Infolists\Components\TextEntry::make('departments_count')
                            ->label('Départements')
                            ->state(fn ($record) => $record->departments()->count()),

                        Infolists\Components\TextEntry::make('inventory_sessions_count')
                            ->label('Sessions d\'inventaire')
                            ->state(fn ($record) => $record->inventorySessions()->count()),

                        Infolists\Components\TextEntry::make('ai_usage_count')
                            ->label('Requêtes IA')
                            ->state(fn ($record) => $record->aiUsageLogs()->count()),
                    ])->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizations::route('/'),
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
