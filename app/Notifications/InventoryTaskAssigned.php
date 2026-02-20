<?php

namespace App\Notifications;

use App\Filament\App\Resources\InventorySessionResource;
use App\Models\InventoryTask;
use App\Models\NotificationTemplate;
use Filament\Notifications\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InventoryTaskAssigned extends Notification
{
    use Queueable;

    public function __construct(
        protected InventoryTask $task,
    ) {}

    public function via(object $notifiable): array
    {
        $template = NotificationTemplate::getOrDefault('task_assigned');

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

        $url = InventorySessionResource::getUrl('execute-task', [
            'record' => $this->task->session_id,
            'taskId' => $this->task->id,
            'tenant' => $this->task->session->organization,
        ]);

        return \Filament\Notifications\Notification::make()
            ->title($rendered['subject'])
            ->body(str_replace("\n", ' ', mb_substr($rendered['body'], 0, 120)) . '...')
            ->icon('heroicon-o-clipboard-document-check')
            ->info()
            ->actions([
                Action::make('scan')
                    ->label('Commencer le scan')
                    ->url($url)
                    ->button(),
            ])
            ->getDatabaseMessage();
    }

    protected function getRendered(object $notifiable): array
    {
        $template = NotificationTemplate::getOrDefault('task_assigned');

        $session = $this->task->session;
        $location = $this->task->location;

        return $template->render([
            'assignee_name' => $notifiable->name,
            'session_name' => $session->name,
            'creator_name' => $session->creator?->name ?? '',
            'location_name' => $location?->name ?? '',
            'location_part' => $location ? " à l'emplacement \"{$location->name}\"" : '',
        ]);
    }
}
