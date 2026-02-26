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
use Illuminate\Support\Collection;

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
        'suggested',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields_schema' => 'array',
            'depreciation_method' => DepreciationMethod::class,
            'suggested' => 'boolean',
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

    public function identificationTags(): HasMany
    {
        return $this->hasMany(AssetTag::class, 'category_id')->orderBy('sort_order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    /**
     * Collect all applicable tags for a given category, including:
     * 1. Global tags (category_id = null) — always first, ordered by sort_order
     * 2. Ancestor chain tags from root to the given category, ordered by sort_order within each level
     */
    public static function getAllTagsForCategory(?string $categoryId, string $organizationId): Collection
    {
        // 1. Global tags
        $globalTags = AssetTag::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNull('category_id')
            ->orderBy('sort_order')
            ->get();

        if (! $categoryId) {
            return $globalTags;
        }

        // 2. Build ancestor chain from current category up to root
        $ancestorIds = [];
        $currentId = $categoryId;
        $visited = [];

        while ($currentId && ! in_array($currentId, $visited)) {
            $visited[] = $currentId;
            $ancestorIds[] = $currentId;
            $currentId = static::withoutGlobalScopes()
                ->where('id', $currentId)
                ->value('parent_id');
        }

        // Reverse so root is first, current category is last
        $ancestorIds = array_reverse($ancestorIds);

        // 3. Fetch all tags for the ancestor chain in one query
        $categoryTags = AssetTag::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('category_id', $ancestorIds)
            ->orderBy('sort_order')
            ->get();

        // 4. Order: maintain ancestor chain order (root first), then sort_order within each level
        $orderedCategoryTags = collect();
        foreach ($ancestorIds as $ancestorId) {
            $tagsForLevel = $categoryTags->where('category_id', $ancestorId)->sortBy('sort_order');
            $orderedCategoryTags = $orderedCategoryTags->merge($tagsForLevel);
        }

        // 5. Merge: global first, then category chain
        return $globalTags->merge($orderedCategoryTags)->values();
    }
}
