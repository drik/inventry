<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $fillable = [
        'organization_id',
        'type',
        'subject',
        'body',
        'email_enabled',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
        ];
    }

    /**
     * Default templates used as fallback when no custom template exists.
     */
    public static function defaults(): array
    {
        return [
            'task_assigned' => [
                'subject' => 'Nouvelle tâche d\'inventaire assignée',
                'body' => "Bonjour {assignee_name},\n\nUne tâche de scan vous a été assignée par {creator_name} pour la session \"{session_name}\"{location_part}.\n\nConnectez-vous à l'application pour commencer le scan.",
            ],
            'task_completed' => [
                'subject' => 'Tâche d\'inventaire terminée',
                'body' => "Bonjour {creator_name},\n\n{assignee_name} a terminé sa tâche de scan{location_part} pour la session \"{session_name}\".",
            ],
            'user_invitation' => [
                'subject' => 'Invitation à rejoindre {organization_name}',
                'body' => "Bonjour,\n\n{inviter_name} vous invite à rejoindre l'organisation \"{organization_name}\" en tant que {role}.\n\nCliquez sur le lien ci-dessous pour accepter l'invitation et créer votre compte.\n\nCette invitation expire le {expires_at}.",
            ],
        ];
    }

    /**
     * Get the template for a given type, or return defaults.
     */
    public static function getOrDefault(string $type): self
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            $template = static::where('type', $type)->first();
            if ($template) {
                return $template;
            }
        }

        $defaults = static::defaults()[$type] ?? ['subject' => '', 'body' => ''];

        $instance = new static();
        $instance->type = $type;
        $instance->subject = $defaults['subject'];
        $instance->body = $defaults['body'];
        $instance->email_enabled = true;

        return $instance;
    }

    /**
     * Render the template by replacing placeholders.
     */
    public function render(array $placeholders): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($placeholders as $key => $value) {
            $subject = str_replace("{{$key}}", $value, $subject);
            $body = str_replace("{{$key}}", $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
