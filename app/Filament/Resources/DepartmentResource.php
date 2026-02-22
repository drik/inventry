<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Départements';

    protected static ?string $modelLabel = 'Département';

    protected static ?string $pluralModelLabel = 'Départements';

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

                Tables\Columns\TextColumn::make('location.name')
                    ->label('Localisation')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Responsable')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Membres')
                    ->counts('users')
                    ->sortable(),
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
                            ->label('Département parent')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('location.name')
                            ->label('Localisation')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('manager.name')
                            ->label('Responsable')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('users_count')
                            ->label('Nombre de membres')
                            ->state(fn ($record) => $record->users()->count()),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
        ];
    }
}
