<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManufacturerResource\Pages;
use App\Models\Manufacturer;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ManufacturerResource extends Resource
{
    protected static ?string $model = Manufacturer::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Fabricants';

    protected static ?string $modelLabel = 'Fabricant';

    protected static ?string $pluralModelLabel = 'Fabricants';

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
                    ->description(fn ($record) => $record->isDefault() ? 'Par défaut' : null),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->sortable()
                    ->searchable()
                    ->placeholder('Global')
                    ->limit(25),

                Tables\Columns\TextColumn::make('website')
                    ->label('Site web')
                    ->url(fn ($record) => $record->website, shouldOpenInNewTab: true)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('support_email')
                    ->label('Email support')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('support_phone')
                    ->label('Tél. support')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Par défaut')
                    ->queries(
                        true: fn ($query) => $query->whereNull('organization_id'),
                        false: fn ($query) => $query->whereNotNull('organization_id'),
                    ),
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
                            ->label('Organisation')
                            ->placeholder('Global (par défaut)'),

                        Infolists\Components\TextEntry::make('website')
                            ->label('Site web')
                            ->url(fn ($record) => $record->website, shouldOpenInNewTab: true)
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('support_email')
                            ->label('Email support')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('support_phone')
                            ->label('Téléphone support')
                            ->placeholder('—'),

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
            'index' => Pages\ListManufacturers::route('/'),
        ];
    }
}
