<?php

namespace App\Notifications;

use App\Filament\App\Resources\InventorySessionResource;
use App\Models\InventoryTask;
use App\Models\NotificationTemplate;
use Filament\Notifications\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InventoryTaskCompleted extends Notification
{
    use Queueable;

    public function __construct(
        protected InventoryTask $task,
    ) {}

    public function via(object $notifiable): array
    {
        $template = NotificationTemplate::getOrDefault('task_completed');

        $channels = ['database'];

        if ($template->email_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rendered = $this->getRendered($notifiable);

        return (new MailMessage())
            ->subject($rendered['subject'])
            ->line($rendered['body']);
    }

    public function toDatabase(object $notifiable): array
    {
        $rendered = $this->getRendered($notifiable);

        $url = InventorySessionResource::getUrl('view', [
            'record' => $this->task->session_id,
            'tenant' => $this->task->session->organization,
        ]);

        return \Filament\Notifications\Notification::make()
            ->title($rendered['subject'])
            ->body(str_replace("\n", ' ', mb_substr($rendered['body'], 0, 120)) . '...')
            ->icon('heroicon-o-check-circle')
            ->success()
            ->actions([
                Action::make('view')
                    ->label('Voir la session')
                    ->url($url)
                    ->button(),
            ])
            ->getDatabaseMessage();
    }

    protected function getRendered(object $notifiable): array
    {
        $template = NotificationTemplate::getOrDefault('task_completed');

        $session = $this->task->session;
        $assignee = $this->task->assignee;
        $location = $this->task->location;

        return $template->render([
            'creator_name' => $notifiable->name,
            'assignee_name' => $assignee?->name ?? '',
            'session_name' => $session->name,
            'location_name' => $location?->name ?? '',
            'location_part' => $location ? " à l'emplacement \"{$location->name}\"" : '',
        ]);
    }
}
