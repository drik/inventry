<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetCategoryResource\Pages;
use App\Models\AssetCategory;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AssetCategoryResource extends Resource
{
    protected static ?string $model = AssetCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Catégories d\'assets';

    protected static ?string $modelLabel = 'Catégorie';

    protected static ?string $pluralModelLabel = 'Catégories d\'assets';

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

                Tables\Columns\TextColumn::make('depreciation_method')
                    ->label('Amortissement')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('default_useful_life_months')
                    ->label('Durée de vie (mois)')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('assets_count')
                    ->label('Assets')
                    ->counts('assets')
                    ->sortable(),

                Tables\Columns\TextColumn::make('identification_tags_count')
                    ->label('Tags ID')
                    ->counts('identificationTags')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('custom_fields_count')
                    ->label('Champs perso.')
                    ->counts('customFields')
                    ->sortable()
                    ->toggleable(),
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
                            ->label('Catégorie parente')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])->columns(2),

                Infolists\Components\Section::make('Amortissement')
                    ->schema([
                        Infolists\Components\TextEntry::make('depreciation_method')
                            ->label('Méthode')
                            ->badge()
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('default_useful_life_months')
                            ->label('Durée de vie par défaut (mois)')
                            ->placeholder('—'),
                    ])->columns(2),

                Infolists\Components\Section::make('Statistiques')
                    ->schema([
                        Infolists\Components\TextEntry::make('assets_count')
                            ->label('Nombre d\'assets')
                            ->state(fn ($record) => $record->assets()->count()),

                        Infolists\Components\TextEntry::make('identification_tags_count')
                            ->label('Tags d\'identification')
                            ->state(fn ($record) => $record->identificationTags()->count()),

                        Infolists\Components\TextEntry::make('custom_fields_count')
                            ->label('Champs personnalisés')
                            ->state(fn ($record) => $record->customFields()->count()),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetCategories::route('/'),
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
