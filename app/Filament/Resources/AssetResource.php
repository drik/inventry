<?php

namespace App\Filament\Resources;

use App\Enums\AssetStatus;
use App\Filament\Resources\AssetResource\Pages;
use App\Models\Asset;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Assets';

    protected static ?string $modelLabel = 'Asset';

    protected static ?string $pluralModelLabel = 'Assets';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('primaryImage.file_path')
                    ->label('Image')
                    ->width(50)
                    ->defaultImageUrl(fn ($record) => null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('asset_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->sortable()
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Catégorie')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('location.name')
                    ->label('Localisation')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Fabricant')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('serial_number')
                    ->label('N° série')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('purchase_cost')
                    ->label('Coût')
                    ->money('USD')
                    ->sortable()
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
                    ->options(AssetStatus::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Catégorie')
                    ->relationship('category', 'name')
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
                Infolists\Components\Section::make('Informations générales')
                    ->schema([
                        Infolists\Components\TextEntry::make('asset_code')
                            ->label('Code asset')
                            ->badge()
                            ->copyable(),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Nom'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Statut')
                            ->badge(),

                        Infolists\Components\TextEntry::make('organization.name')
                            ->label('Organisation'),

                        Infolists\Components\TextEntry::make('category.name')
                            ->label('Catégorie')
                            ->badge(),

                        Infolists\Components\TextEntry::make('location.name')
                            ->label('Localisation')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('department.name')
                            ->label('Département')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('manufacturer.name')
                            ->label('Fabricant')
                            ->placeholder('—'),
                    ])->columns(2),

                Infolists\Components\Section::make('Identification')
                    ->schema([
                        Infolists\Components\TextEntry::make('serial_number')
                            ->label('Numéro de série')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('sku')
                            ->label('SKU')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('barcode')
                            ->label('Code-barres')
                            ->placeholder('—'),
                    ])->columns(3),

                Infolists\Components\Section::make('Financier')
                    ->schema([
                        Infolists\Components\TextEntry::make('purchase_date')
                            ->label('Date d\'achat')
                            ->date('d/m/Y')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('purchase_cost')
                            ->label('Coût d\'achat')
                            ->money('USD')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('warranty_expiry')
                            ->label('Garantie expire le')
                            ->date('d/m/Y')
                            ->placeholder('—')
                            ->color(fn ($record) => $record->warranty_expiry?->isPast() ? 'danger' : null),

                        Infolists\Components\TextEntry::make('depreciation_method')
                            ->label('Méthode d\'amortissement')
                            ->badge()
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('useful_life_months')
                            ->label('Durée de vie (mois)')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('salvage_value')
                            ->label('Valeur résiduelle')
                            ->money('USD')
                            ->placeholder('—'),
                    ])->columns(3),

                Infolists\Components\Section::make('Assignation actuelle')
                    ->schema([
                        Infolists\Components\TextEntry::make('currentAssignment.assignee.name')
                            ->label('Assigné à')
                            ->placeholder('Non assigné'),

                        Infolists\Components\TextEntry::make('currentAssignment.assigned_at')
                            ->label('Assigné le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ])->columns(2),

                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->hiddenLabel()
                            ->html()
                            ->placeholder('Aucune note.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
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
