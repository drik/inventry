<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Assignment extends Model
{
    use BelongsToOrganization, HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'asset_id',
        'assignee_type',
        'assignee_id',
        'assigned_by',
        'assigned_at',
        'expected_return_at',
        'returned_at',
        'return_condition',
        'return_accepted_by',
        'notes',
        'signature_path',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'expected_return_at' => 'date',
            'returned_at' => 'datetime',
        ];
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }

    public function scopeReturned(Builder $query): Builder
    {
        return $query->whereNotNull('returned_at');
    }

    // Relationships

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignee(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function returnAcceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_accepted_by');
    }
}
