<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionChanged extends Notification
{
    use Queueable;

    public function __construct(
        protected Organization $organization,
        protected string $newPlanName,
        protected string $action = 'changed',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $actionLabel = match ($this->action) {
            'upgraded' => 'mis à niveau',
            'downgraded' => 'rétrogradé',
            'cancelled' => 'annulé',
            'resumed' => 'repris',
            default => 'modifié',
        };

        return (new MailMessage)
            ->subject("Abonnement {$actionLabel} - Inventry")
            ->greeting('Bonjour,')
            ->line("L'abonnement de votre organisation \"{$this->organization->name}\" a été {$actionLabel}.")
            ->line("Nouveau plan : {$this->newPlanName}")
            ->action('Voir mon abonnement', url('/app'))
            ->line('Merci de votre confiance !');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Abonnement modifié',
            'body' => "Votre plan a été changé pour {$this->newPlanName}.",
            'type' => 'subscription_changed',
            'organization_id' => $this->organization->id,
            'new_plan' => $this->newPlanName,
            'action' => $this->action,
        ];
    }
}
