<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use BelongsToOrganization, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'parent_id',
        'name',
        'address',
        'city',
        'country',
        'coordinates',
        'contact_person',
        'contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'coordinates' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function assignments(): MorphMany
    {
        return $this->morphMany(Assignment::class, 'assignee');
    }
}
