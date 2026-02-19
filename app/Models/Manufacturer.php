<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Manufacturer extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'name',
        'website',
        'support_email',
        'support_phone',
        'notes',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            $tenant = Filament::getTenant();

            if ($tenant) {
                $builder->where(function (Builder $query) use ($tenant) {
                    $query->whereNull('manufacturers.organization_id')
                        ->orWhere('manufacturers.organization_id', $tenant->id);
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
}
