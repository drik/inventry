<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $fillable = [
        'organization_id',
        'email',
        'role',
        'token',
        'invited_by',
        'status',
        'accepted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'role' => UserRole::class,
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation) {
            if (! $invitation->token) {
                $invitation->token = Str::random(64);
            }
            if (! $invitation->expires_at) {
                $invitation->expires_at = now()->addDays(7);
            }
            if (! $invitation->status) {
                $invitation->status = InvitationStatus::Pending;
            }
        });
    }

    // Relationships

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', InvitationStatus::Pending);
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    // Helpers

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending && ! $this->isExpired();
    }

    public function markAsAccepted(): void
    {
        $this->update([
            'status' => InvitationStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => InvitationStatus::Cancelled,
        ]);
    }

    public function getAcceptUrl(): string
    {
        return url("/invitations/{$this->token}/accept");
    }
}
