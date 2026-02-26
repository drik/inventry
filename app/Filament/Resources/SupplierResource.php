<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Fournisseurs';

    protected static ?string $modelLabel = 'Fournisseur';

    protected static ?string $pluralModelLabel = 'Fournisseurs';

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
                    ->size(40),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('suggested')
                    ->label('IA')
                    ->icon(fn ($state): ?string => match ($state) {
                        true => 'heroicon-o-sparkles',
                        false => 'heroicon-o-check-circle',
                        default => null,
                    })
                    ->color(fn ($state): ?string => match ($state) {
                        true => 'warning',
                        false => 'success',
                        default => null,
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->sortable()
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Contact')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Téléphone')
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

                Tables\Filters\TernaryFilter::make('suggested')
                    ->label('Suggestion IA')
                    ->trueLabel('Non confirmées')
                    ->falseLabel('Confirmées')
                    ->placeholder('Toutes'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Général')
                    ->schema([
                        Infolists\Components\ImageEntry::make('logo_path')
                            ->label('Logo')
                            ->circular()
                            ->size(80),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Nom'),

                        Infolists\Components\TextEntry::make('organization.name')
                            ->label('Organisation'),

                        Infolists\Components\TextEntry::make('contact_name')
                            ->label('Contact')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('phone')
                            ->label('Téléphone')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('fax')
                            ->label('Fax')
                            ->placeholder('—'),
                    ])->columns(2),

                Infolists\Components\Section::make('Détails')
                    ->schema([
                        Infolists\Components\TextEntry::make('address')
                            ->label('Adresse')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('url')
                            ->label('Site web')
                            ->url(fn ($record) => $record->url, shouldOpenInNewTab: true)
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
            'index' => Pages\ListSuppliers::route('/'),
        ];
    }
}
