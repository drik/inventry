<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryNote extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'notable_type',
        'notable_id',
        'content',
        'original_content',
        'source_type',
        'source_media_id',
        'ai_usage_log_id',
        'created_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sourceMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'source_media_id');
    }

    public function aiUsageLog(): BelongsTo
    {
        return $this->belongsTo(AiUsageLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
