<?php

namespace App\Models;

use App\Enums\EncodingMode;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetTag extends Model
{
    use BelongsToOrganization, HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'category_id',
        'name',
        'description',
        'is_required',
        'encoding_mode',
        'sort_order',
        'is_system',
        'is_unique',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_system' => 'boolean',
            'is_unique' => 'boolean',
            'encoding_mode' => EncodingMode::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (AssetTag $tag) {
            if ($tag->is_system) {
                throw new \RuntimeException('System tags cannot be deleted.');
            }
        });
    }

    // Scopes

    public function scopeGlobal($query)
    {
        return $query->whereNull('category_id');
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    // Relationships

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(AssetTagValue::class, 'asset_tag_id');
    }
}
