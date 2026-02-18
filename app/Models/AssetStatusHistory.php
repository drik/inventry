<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetStatusHistory extends Model
{
    use BelongsToOrganization, HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'asset_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => AssetStatus::class,
            'to_status' => AssetStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
