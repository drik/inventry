<?php

namespace App\Models;

use App\Enums\InventoryItemStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    use BelongsToOrganization, HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'session_id',
        'task_id',
        'asset_id',
        'status',
        'scanned_at',
        'scanned_by',
        'condition_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InventoryItemStatus::class,
            'scanned_at' => 'datetime',
        ];
    }

    // Relationships

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class, 'session_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(InventoryTask::class, 'task_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
