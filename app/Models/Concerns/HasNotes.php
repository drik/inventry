<?php

namespace App\Models\Concerns;

use App\Models\InventoryNote;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotes
{
    public function notes(): MorphMany
    {
        return $this->morphMany(InventoryNote::class, 'notable');
    }
}
