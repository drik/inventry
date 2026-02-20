<?php

namespace App\Filament\App\Resources;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Filament\App\Resources\InvitationResource\Pages;
use App\Models\Invitation;
use App\Notifications\UserInvitation;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $pluralModelLabel = 'Invitations';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRoleAtLeast(UserRole::Admin);
    }

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
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invitedBy.name')
                    ->label('Invited by')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->since()
                    ->sortable()
                    ->description(fn (Invitation $record) => $record->isExpired() && $record->status === InvitationStatus::Pending ? 'Expired' : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(InvitationStatus::class)
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('A new invitation email will be sent.')
                    ->action(function (Invitation $record): void {
                        $record->update(['expires_at' => now()->addDays(7)]);

                        NotificationFacade::route('mail', $record->email)
                            ->notify(new UserInvitation($record));

                        Notification::make()
                            ->title("Invitation resent to {$record->email}.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Invitation $record) => $record->status === InvitationStatus::Pending),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Invitation $record): void {
                        $record->markAsCancelled();

                        Notification::make()
                            ->title("Invitation to {$record->email} cancelled.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Invitation $record) => $record->status === InvitationStatus::Pending),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvitations::route('/'),
        ];
    }
}
