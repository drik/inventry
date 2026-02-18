<?php

namespace App\Models;

use App\Enums\InventorySessionStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySession extends Model
{
    use BelongsToOrganization, HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'status',
        'scope_type',
        'scope_ids',
        'created_by',
        'started_at',
        'completed_at',
        'total_expected',
        'total_scanned',
        'total_matched',
        'total_missing',
        'total_unexpected',
    ];

    protected function casts(): array
    {
        return [
            'status' => InventorySessionStatus::class,
            'scope_ids' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Relationships

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(InventoryTask::class, 'session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'session_id');
    }
}
