<?php

namespace App\Filament\App\Pages;

use App\Models\NotificationTemplate;
use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageNotificationTemplates extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Notification Templates';

    protected static ?string $title = 'Notification Templates';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.app.pages.manage-notification-templates';

    public ?array $task_assigned = [];

    public ?array $task_completed = [];

    public ?array $user_invitation = [];

    public function mount(): void
    {
        $this->loadTemplates();
    }

    protected function loadTemplates(): void
    {
        $assignedTemplate = NotificationTemplate::getOrDefault('task_assigned');
        $completedTemplate = NotificationTemplate::getOrDefault('task_completed');

        $this->task_assigned = [
            'email_enabled' => $assignedTemplate->email_enabled,
            'subject' => $assignedTemplate->subject,
            'body' => $assignedTemplate->body,
        ];

        $this->task_completed = [
            'email_enabled' => $completedTemplate->email_enabled,
            'subject' => $completedTemplate->subject,
            'body' => $completedTemplate->body,
        ];

        $invitationTemplate = NotificationTemplate::getOrDefault('user_invitation');

        $this->user_invitation = [
            'email_enabled' => $invitationTemplate->email_enabled,
            'subject' => $invitationTemplate->subject,
            'body' => $invitationTemplate->body,
        ];
    }

    public function taskAssignedForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Task Assigned Notification')
                    ->description('Sent to users when they are assigned a scan task.')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Toggle::make('email_enabled')
                            ->label('Send by email')
                            ->helperText('When disabled, only the in-app notification is sent.'),
                        TextInput::make('subject')
                            ->label('Email subject')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('body')
                            ->label('Email body')
                            ->required()
                            ->rows(6)
                            ->helperText('Available placeholders: {assignee_name}, {creator_name}, {session_name}, {location_name}, {location_part}'),
                    ]),
            ])
            ->statePath('task_assigned');
    }

    public function taskCompletedForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Task Completed Notification')
                    ->description('Sent to the session creator when a user completes their scan task.')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        Toggle::make('email_enabled')
                            ->label('Send by email')
                            ->helperText('When disabled, only the in-app notification is sent.'),
                        TextInput::make('subject')
                            ->label('Email subject')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('body')
                            ->label('Email body')
                            ->required()
                            ->rows(6)
                            ->helperText('Available placeholders: {creator_name}, {assignee_name}, {session_name}, {location_name}, {location_part}'),
                    ]),
            ])
            ->statePath('task_completed');
    }

    public function userInvitationForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('User Invitation')
                    ->description('Sent to users when they are invited to join the organization.')
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        Toggle::make('email_enabled')
                            ->label('Send by email')
                            ->helperText('When disabled, invitations will not be sent.'),
                        TextInput::make('subject')
                            ->label('Email subject')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('body')
                            ->label('Email body')
                            ->required()
                            ->rows(6)
                            ->helperText('Available placeholders: {organization_name}, {inviter_name}, {role}, {email}, {expires_at}'),
                    ]),
            ])
            ->statePath('user_invitation');
    }

    protected function getForms(): array
    {
        return [
            'taskAssignedForm',
            'taskCompletedForm',
            'userInvitationForm',
        ];
    }

    public function save(): void
    {
        $orgId = Filament::getTenant()->id;

        foreach (['task_assigned', 'task_completed', 'user_invitation'] as $type) {
            $data = $this->{$type};

            NotificationTemplate::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'type' => $type,
                ],
                [
                    'subject' => $data['subject'],
                    'body' => $data['body'],
                    'email_enabled' => $data['email_enabled'],
                ],
            );
        }

        Notification::make()
            ->title('Templates saved successfully.')
            ->success()
            ->send();
    }

    public function resetToDefaults(): void
    {
        $orgId = Filament::getTenant()->id;

        NotificationTemplate::where('organization_id', $orgId)
            ->whereIn('type', ['task_assigned', 'task_completed', 'user_invitation'])
            ->delete();

        $this->loadTemplates();

        Notification::make()
            ->title('Templates reset to defaults.')
            ->success()
            ->send();
    }
}
