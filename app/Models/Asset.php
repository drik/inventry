<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\DepreciationMethod;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Asset extends Model
{
    use BelongsToOrganization, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'asset_code',
        'name',
        'category_id',
        'model_id',
        'location_id',
        'department_id',
        'serial_number',
        'sku',
        'status',
        'purchase_date',
        'purchase_cost',
        'vendor_id',
        'warranty_expiry',
        'depreciation_method',
        'useful_life_months',
        'salvage_value',
        'retirement_date',
        'barcode',
        'qr_code_path',
        'notes',
        'custom_field_values',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'depreciation_method' => DepreciationMethod::class,
            'purchase_date' => 'date',
            'warranty_expiry' => 'date',
            'retirement_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'custom_field_values' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Asset $asset) {
            if (empty($asset->asset_code)) {
                $asset->asset_code = static::generateAssetCode($asset->organization_id);
            }
            if (empty($asset->barcode)) {
                $asset->barcode = static::generateBarcode($asset->organization_id);
            }
        });
    }

    public static function generateAssetCode(string $organizationId): string
    {
        $last = static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderByDesc('asset_code')
            ->value('asset_code');

        $nextNumber = 1;
        if ($last && preg_match('/AST-(\d+)/', $last, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        }

        return 'AST-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    public static function generateBarcode(string $organizationId): string
    {
        do {
            $barcode = strtoupper(Str::random(12));
        } while (
            static::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('barcode', $barcode)
                ->exists()
        );

        return $barcode;
    }

    // Relationships

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(AssetImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(AssetImage::class)->where('is_primary', true);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(AssetTag::class, 'taggable');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AssetStatusHistory::class)->orderByDesc('created_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(Assignment::class)->whereNull('returned_at')->latestOfMany('assigned_at');
    }
}
