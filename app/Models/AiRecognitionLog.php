<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecognitionLog extends Model
{
    use BelongsToOrganization, HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'task_id',
        'user_id',
        'captured_image_path',
        'use_case',
        'provider',
        'model',
        'used_fallback',
        'ai_response',
        'matched_asset_ids',
        'selected_asset_id',
        'selected_action',
        'prompt_tokens',
        'completion_tokens',
        'estimated_cost_usd',
        'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'used_fallback' => 'boolean',
            'ai_response' => 'array',
            'matched_asset_ids' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
            'latency_ms' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(InventoryTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function selectedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'selected_asset_id');
    }
}
