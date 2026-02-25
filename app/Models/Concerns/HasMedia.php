<?php

namespace App\Models\Concerns;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function photos(): MorphMany
    {
        return $this->media()->where('collection', 'photos');
    }

    public function audioFiles(): MorphMany
    {
        return $this->media()->where('collection', 'audio');
    }

    public function videos(): MorphMany
    {
        return $this->media()->where('collection', 'video');
    }

    public function documents(): MorphMany
    {
        return $this->media()->where('collection', 'documents');
    }
}
