<?php

namespace App\Livewire;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationAlertManager extends Component
{
    public function checkNewNotifications(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $newNotifications = $user->notifications()
            ->whereNull('shown_at')
            ->latest()
            ->get();

        foreach ($newNotifications as $dbNotification) {
            $data = $dbNotification->data;

            $notification = Notification::make()
                ->title($data['title'] ?? '')
                ->body($data['body'] ?? null)
                ->persistent();

            if (! empty($data['icon'])) {
                $notification->icon($data['icon']);
            }

            if (! empty($data['iconColor'])) {
                $notification->iconColor($data['iconColor']);
            }

            if (! empty($data['status'])) {
                match ($data['status']) {
                    'success' => $notification->success(),
                    'warning' => $notification->warning(),
                    'danger' => $notification->danger(),
                    'info' => $notification->info(),
                    default => null,
                };
            }

            if (! empty($data['actions'])) {
                $actions = [];

                foreach ($data['actions'] as $actionData) {
                    $action = Action::make($actionData['name'] ?? 'action')
                        ->label($actionData['label'] ?? '');

                    if (! empty($actionData['url'])) {
                        $action->url($actionData['url']);

                        if (! empty($actionData['shouldOpenUrlInNewTab'])) {
                            $action->openUrlInNewTab();
                        }
                    }

                    if (! empty($actionData['color'])) {
                        $action->color($actionData['color']);
                    }

                    if (! empty($actionData['isOutlined'])) {
                        $action->outlined();
                    }

                    $action->button();

                    $actions[] = $action;
                }

                $notification->actions($actions);
            }

            $notification->send();

            $dbNotification->update(['shown_at' => now()]);
        }
    }

    public function render()
    {
        return view('livewire.notification-alert-manager');
    }
}
