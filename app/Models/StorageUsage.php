<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageUsage extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'used_bytes',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'used_bytes' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
