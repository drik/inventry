<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'media';

    protected $fillable = [
        'organization_id',
        'mediable_type',
        'mediable_id',
        'collection',
        'disk',
        'file_path',
        'file_name',
        'mime_type',
        'size_bytes',
        'metadata',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size_bytes' => 'integer',
        ];
    }

    // Relationships

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Accessors

    public function getUrlAttribute(): string
    {
        if ($this->disk === 's3' && config('filesystems.disks.s3.bucket')) {
            return Storage::disk('s3')->temporaryUrl($this->file_path, now()->addHour());
        }

        // Fall back to public disk if S3 not configured
        $disk = ($this->disk === 's3' && ! config('filesystems.disks.s3.bucket'))
            ? 'public'
            : $this->disk;

        return Storage::disk($disk)->url($this->file_path);
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size_bytes;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' Go';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' Mo';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0) . ' Ko';
        }

        return $bytes . ' o';
    }

    // Scopes

    public function scopePhotos($query)
    {
        return $query->where('collection', 'photos');
    }

    public function scopeAudio($query)
    {
        return $query->where('collection', 'audio');
    }

    public function scopeVideo($query)
    {
        return $query->where('collection', 'video');
    }

    public function scopeDocuments($query)
    {
        return $query->where('collection', 'documents');
    }
}
