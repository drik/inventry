<?php

namespace App\Models;

use App\Enums\DepreciationMethod;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetCategory extends Model
{
    use BelongsToOrganization, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'parent_id',
        'name',
        'description',
        'icon',
        'custom_fields_schema',
        'depreciation_method',
        'default_useful_life_months',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields_schema' => 'array',
            'depreciation_method' => DepreciationMethod::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AssetCategory::class, 'parent_id');
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class, 'category_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }
}
