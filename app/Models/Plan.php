<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'limits',
        'is_active',
        'sort_order',
        'paddle_monthly_price_id',
        'paddle_yearly_price_id',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // Accessors

    public function getFormattedMonthlyPriceAttribute(): string
    {
        if ($this->price_monthly === 0) {
            return 'Gratuit';
        }

        return number_format($this->price_monthly / 100, 2, ',', ' ') . ' €';
    }

    public function getFormattedYearlyPriceAttribute(): string
    {
        if ($this->price_yearly === 0) {
            return 'Gratuit';
        }

        return number_format($this->price_yearly / 100, 2, ',', ' ') . ' €';
    }

    // Methods

    public function getLimit(string $key): int
    {
        return $this->limits[$key] ?? 0;
    }

    public function hasFeature(string $key): bool
    {
        return (bool) ($this->limits[$key] ?? false);
    }

    public function isFreemium(): bool
    {
        return $this->slug === 'freemium';
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }
}
