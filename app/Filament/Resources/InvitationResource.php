<?php

namespace App\Filament\Resources;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Filament\Resources\InvitationResource\Pages;
use App\Models\Invitation;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Invitations';

    protected static ?string $modelLabel = 'Invitation';

    protected static ?string $pluralModelLabel = 'Invitations';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('role')
                    ->label('Rôle')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invitedBy.name')
                    ->label('Invité par')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color(fn ($record) => $record->expires_at?->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('accepted_at')
                    ->label('Accepté le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
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
                    ->options(InvitationStatus::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('role')
                    ->label('Rôle')
                    ->options(UserRole::class),
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
                        Infolists\Components\TextEntry::make('email')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('organization.name')
                            ->label('Organisation'),

                        Infolists\Components\TextEntry::make('role')
                            ->label('Rôle')
                            ->badge(),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Statut')
                            ->badge(),

                        Infolists\Components\TextEntry::make('invitedBy.name')
                            ->label('Invité par')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('token')
                            ->label('Token')
                            ->copyable()
                            ->limit(20),
                    ])->columns(2),

                Infolists\Components\Section::make('Dates')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),

                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('Expire le')
                            ->dateTime('d/m/Y H:i')
                            ->color(fn ($record) => $record->expires_at?->isPast() ? 'danger' : null),

                        Infolists\Components\TextEntry::make('accepted_at')
                            ->label('Accepté le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non accepté'),
                    ])->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvitations::route('/'),
        ];
    }
}
