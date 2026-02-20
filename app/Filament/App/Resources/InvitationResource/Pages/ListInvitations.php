<?php

namespace App\Filament\App\Resources\InvitationResource\Pages;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Filament\App\Resources\InvitationResource;
use App\Filament\App\Resources\UserResource;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\UserInvitation;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        $currentUser = auth()->user();
        $assignableRoles = UserRole::assignableBy($currentUser->role);

        return [
            Actions\Action::make('users')
                ->label('Users')
                ->icon('heroicon-o-users')
                ->color('gray')
                ->url(UserResource::getUrl('index', ['tenant' => Filament::getTenant()])),

            Actions\Action::make('inviteUsers')
                ->label('Invite Users')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->form([
                    Forms\Components\TagsInput::make('emails')
                        ->label('Email Addresses')
                        ->placeholder('Type email and press Enter')
                        ->required()
                        ->nestedRecursiveRules(['email']),

                    Forms\Components\Select::make('role')
                        ->label('Role')
                        ->options(
                            collect($assignableRoles)
                                ->mapWithKeys(fn (UserRole $r) => [$r->value => $r->getLabel()])
                                ->all()
                        )
                        ->required()
                        ->default(UserRole::User->value),
                ])
                ->action(function (array $data): void {
                    $tenant = Filament::getTenant();
                    $currentUser = auth()->user();
                    $role = UserRole::from($data['role']);

                    if ($role->level() > $currentUser->role->level()) {
                        Notification::make()
                            ->title('Cannot assign a role higher than your own.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $sent = 0;
                    $skipped = [];

                    foreach ($data['emails'] as $email) {
                        $email = strtolower(trim($email));

                        if (User::where('organization_id', $tenant->id)->where('email', $email)->exists()) {
                            $skipped[] = "{$email} (already a member)";

                            continue;
                        }

                        if (Invitation::where('email', $email)
                            ->where('status', InvitationStatus::Pending)
                            ->where('expires_at', '>', now())
                            ->exists()
                        ) {
                            $skipped[] = "{$email} (already invited)";

                            continue;
                        }

                        $invitation = Invitation::create([
                            'organization_id' => $tenant->id,
                            'email' => $email,
                            'role' => $role,
                            'invited_by' => $currentUser->id,
                        ]);

                        NotificationFacade::route('mail', $email)
                            ->notify(new UserInvitation($invitation));

                        $sent++;
                    }

                    $message = "{$sent} invitation(s) sent.";
                    if (count($skipped) > 0) {
                        $message .= ' Skipped: ' . implode(', ', $skipped);
                    }

                    Notification::make()
                        ->title($message)
                        ->success()
                        ->send();
                })
                ->modalHeading('Invite Users')
                ->modalDescription('Enter email addresses to invite. Each invitee will receive an email with a link to join the organization.')
                ->modalSubmitActionLabel('Send Invitations'),
        ];
    }
}
