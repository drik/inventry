<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEnding extends Notification
{
    use Queueable;

    public function __construct(
        protected Organization $organization,
        protected int $daysRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Votre essai gratuit se termine dans {$this->daysRemaining} jours - Inventry")
            ->greeting('Bonjour,')
            ->line("L'essai gratuit de votre organisation \"{$this->organization->name}\" se termine dans {$this->daysRemaining} jours.")
            ->line('Pour continuer à profiter de toutes les fonctionnalités, souscrivez à un plan payant.')
            ->action('Voir les plans', url('/app'))
            ->line('Sans abonnement, votre organisation passera automatiquement au plan Freemium.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Essai gratuit bientôt terminé',
            'body' => "Votre essai se termine dans {$this->daysRemaining} jours. Souscrivez pour continuer.",
            'type' => 'trial_ending',
            'organization_id' => $this->organization->id,
            'days_remaining' => $this->daysRemaining,
        ];
    }
}
