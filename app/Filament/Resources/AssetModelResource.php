<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetModelResource\Pages;
use App\Models\AssetModel;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssetModelResource extends Resource
{
    protected static ?string $model = AssetModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Modèles';

    protected static ?string $modelLabel = 'Modèle';

    protected static ?string $pluralModelLabel = 'Modèles';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->width(50),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('model_number')
                    ->label('N° modèle')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->sortable()
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Catégorie')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Fabricant')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('end_of_life_months')
                    ->label('Fin de vie')
                    ->suffix(' mois')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assets_count')
                    ->counts('assets')
                    ->label('Assets')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Catégorie'),

                Tables\Filters\SelectFilter::make('manufacturer_id')
                    ->relationship('manufacturer', 'name')
                    ->label('Fabricant'),
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
                        Infolists\Components\ImageEntry::make('image_path')
                            ->label('Image')
                            ->square()
                            ,

                        Infolists\Components\TextEntry::make('name')
                            ->label('Nom'),

                        Infolists\Components\TextEntry::make('model_number')
                            ->label('N° modèle')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('organization.name')
                            ->label('Organisation'),

                        Infolists\Components\TextEntry::make('category.name')
                            ->label('Catégorie')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('manufacturer.name')
                            ->label('Fabricant')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('end_of_life_months')
                            ->label('Fin de vie')
                            ->suffix(' mois')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('assets_count')
                            ->label('Assets')
                            ->state(fn ($record) => $record->assets()->count()),

                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetModels::route('/'),
        ];
    }
}
