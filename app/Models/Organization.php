<?php

namespace App\Models;

use Filament\Models\Contracts\HasCurrentTenantLabel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model implements HasCurrentTenantLabel
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'plan_id',
        'logo_path',
        'address',
        'phone',
        'settings',
    ];

    protected $attributes = [
        'settings' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function getCurrentTenantLabel(): string
    {
        return 'Current organization';
    }

    // Relationships

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
