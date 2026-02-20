<?php

namespace App\Notifications;

use App\Models\Invitation;
use App\Models\NotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    use Queueable;

    public function __construct(
        protected Invitation $invitation,
    ) {}

    public function via(object $notifiable): array
    {
        $template = NotificationTemplate::getOrDefault('user_invitation');

        $channels = [];

        if ($template->email_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rendered = $this->getRendered();

        return (new MailMessage())
            ->subject($rendered['subject'])
            ->line($rendered['body'])
            ->action('Accept Invitation', $this->invitation->getAcceptUrl());
    }

    protected function getRendered(): array
    {
        $template = NotificationTemplate::getOrDefault('user_invitation');

        $organization = $this->invitation->organization;
        $inviter = $this->invitation->invitedBy;

        return $template->render([
            'organization_name' => $organization->name,
            'inviter_name' => $inviter?->name ?? '',
            'role' => $this->invitation->role->getLabel(),
            'email' => $this->invitation->email,
            'expires_at' => $this->invitation->expires_at->format('d/m/Y'),
        ]);
    }
}
