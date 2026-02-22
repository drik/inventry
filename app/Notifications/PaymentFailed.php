<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailed extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Échec du paiement - Inventry')
            ->greeting('Bonjour,')
            ->line('Nous n\'avons pas pu traiter votre paiement pour votre abonnement Inventry.')
            ->line('Veuillez mettre à jour vos informations de paiement pour éviter une interruption de service.')
            ->action('Mettre à jour le paiement', url('/app'))
            ->line('Si vous avez des questions, n\'hésitez pas à nous contacter.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Échec du paiement',
            'body' => 'Votre paiement n\'a pas pu être traité. Veuillez mettre à jour vos informations de paiement.',
            'type' => 'payment_failed',
        ];
    }
}
