<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCondition extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'sort_order',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Same pattern as Manufacturer: show global (null) + org-specific
        static::addGlobalScope('organization', function (Builder $builder) {
            $tenant = Filament::getTenant();

            if ($tenant) {
                $builder->where(function (Builder $query) use ($tenant) {
                    $query->whereNull('asset_conditions.organization_id')
                        ->orWhere('asset_conditions.organization_id', $tenant->id);
                });
            }
        });

        static::creating(function ($model) {
            if (! $model->organization_id) {
                $tenant = Filament::getTenant();
                if ($tenant) {
                    $model->organization_id = $tenant->id;
                }
            }
        });
    }

    public function isDefault(): bool
    {
        return $this->organization_id === null;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'condition_id');
    }

    public static function getDefaultConditions(): array
    {
        return [
            ['name' => 'Neuf', 'slug' => 'new', 'color' => '#3b82f6', 'icon' => 'heroicon-o-sparkles', 'sort_order' => 0],
            ['name' => 'Bon état', 'slug' => 'good', 'color' => '#22c55e', 'icon' => 'heroicon-o-check-circle', 'sort_order' => 1],
            ['name' => 'Usé', 'slug' => 'worn', 'color' => '#f59e0b', 'icon' => 'heroicon-o-minus-circle', 'sort_order' => 2],
            ['name' => 'Endommagé', 'slug' => 'damaged', 'color' => '#ef4444', 'icon' => 'heroicon-o-exclamation-triangle', 'sort_order' => 3],
            ['name' => 'Non fonctionnel', 'slug' => 'non_functional', 'color' => '#dc2626', 'icon' => 'heroicon-o-x-circle', 'sort_order' => 4],
            ['name' => 'Hors service', 'slug' => 'out_of_service', 'color' => '#6b7280', 'icon' => 'heroicon-o-no-symbol', 'sort_order' => 5],
        ];
    }
}
