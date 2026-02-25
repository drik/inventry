<?php

namespace App\Models;

use App\Enums\EncodingMode;
use Filament\Models\Contracts\HasCurrentTenantLabel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Paddle\Billable;

class Organization extends Model implements HasCurrentTenantLabel
{
    use Billable, HasFactory, HasUlids, SoftDeletes;

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

    protected static function booted(): void
    {
        static::created(function (Organization $organization) {
            AssetTag::withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'category_id' => null,
                'name' => 'Serial Number',
                'description' => 'Manufacturer serial number',
                'is_required' => false,
                'encoding_mode' => EncodingMode::QrCode,
                'sort_order' => 1,
                'is_system' => true,
            ]);

            AssetTag::withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'category_id' => null,
                'name' => 'SKU',
                'description' => 'Stock Keeping Unit',
                'is_required' => false,
                'encoding_mode' => EncodingMode::EAN13,
                'sort_order' => 2,
                'is_system' => true,
            ]);

            // Create default asset conditions
            foreach (AssetCondition::getDefaultConditions() as $condition) {
                AssetCondition::withoutGlobalScopes()->create([
                    'organization_id' => $organization->id,
                    'is_default' => true,
                    ...$condition,
                ]);
            }

            // Create storage usage tracking
            StorageUsage::create([
                'organization_id' => $organization->id,
                'used_bytes' => 0,
                'updated_at' => now(),
            ]);
        });
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

    public function assetCategories(): HasMany
    {
        return $this->hasMany(AssetCategory::class);
    }

    public function manufacturers(): HasMany
    {
        return $this->hasMany(Manufacturer::class);
    }

    public function assetModels(): HasMany
    {
        return $this->hasMany(AssetModel::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function assetImages(): HasMany
    {
        return $this->hasMany(AssetImage::class);
    }

    public function assetTags(): HasMany
    {
        return $this->hasMany(AssetTag::class);
    }

    public function assetTagValues(): HasMany
    {
        return $this->hasMany(AssetTagValue::class);
    }

    public function assetStatusHistories(): HasMany
    {
        return $this->hasMany(AssetStatusHistory::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function inventorySessions(): HasMany
    {
        return $this->hasMany(InventorySession::class);
    }

    public function inventoryTasks(): HasMany
    {
        return $this->hasMany(InventoryTask::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function assetConditions(): HasMany
    {
        return $this->hasMany(AssetCondition::class);
    }

    public function storageUsage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StorageUsage::class);
    }

    public function inventoryReports(): HasMany
    {
        return $this->hasMany(InventoryReport::class);
    }
}
