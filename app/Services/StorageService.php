<?php

namespace App\Services;

use App\Enums\PlanFeature;
use App\Models\Media;
use App\Models\Organization;
use App\Models\StorageUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    public function __construct(
        protected PlanLimitService $planLimitService,
    ) {}

    public function upload(
        Organization $org,
        UploadedFile $file,
        string $collection,
        Model $mediable,
        string $uploadedBy,
    ): Media {
        $fileSizeBytes = $file->getSize();

        if (! $this->canUpload($org, $fileSizeBytes)) {
            throw new \RuntimeException('Quota de stockage dépassé. Veuillez passer à un plan supérieur.');
        }

        $disk = config('media.disk', 's3');
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $path = implode('/', [
            $org->id,
            $collection,
            now()->format('Y/m/d'),
            Str::uuid() . '.' . $extension,
        ]);

        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        $media = Media::create([
            'organization_id' => $org->id,
            'mediable_type' => get_class($mediable),
            'mediable_id' => $mediable->id,
            'collection' => $collection,
            'disk' => $disk,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $this->resolveMimeType($file, $collection),
            'size_bytes' => $fileSizeBytes,
            'metadata' => [],
            'uploaded_by' => $uploadedBy,
        ]);

        $this->incrementUsage($org, $fileSizeBytes);

        return $media;
    }

    public function delete(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->file_path);

        $orgId = $media->organization_id;
        $sizeBytes = $media->size_bytes;

        $media->delete();

        StorageUsage::where('organization_id', $orgId)
            ->decrement('used_bytes', $sizeBytes);
    }

    public function canUpload(Organization $org, int $fileSizeBytes): bool
    {
        $limit = $this->planLimitService->getLimit($org, PlanFeature::MaxStorageMb);

        // -1 or 0 (not configured) = unlimited — storage is allowed by default
        // Plans that want to restrict storage must explicitly set a positive limit
        if ($limit <= 0) {
            return true;
        }

        $usedBytes = StorageUsage::where('organization_id', $org->id)->value('used_bytes') ?? 0;
        $limitBytes = $limit * 1048576;

        return ($usedBytes + $fileSizeBytes) <= $limitBytes;
    }

    public function getUsageStats(Organization $org): array
    {
        $usedBytes = StorageUsage::where('organization_id', $org->id)->value('used_bytes') ?? 0;
        $limit = $this->planLimitService->getLimit($org, PlanFeature::MaxStorageMb);
        $quotaBytes = $limit === -1 ? null : $limit * 1048576;

        return [
            'used_bytes' => $usedBytes,
            'quota_bytes' => $quotaBytes,
            'percentage' => $quotaBytes ? min(round(($usedBytes / $quotaBytes) * 100), 100) : 0,
            'remaining_bytes' => $quotaBytes ? max(0, $quotaBytes - $usedBytes) : null,
            'is_unlimited' => $limit === -1,
        ];
    }

    public function getSignedUrl(Media $media, int $expirationMinutes = 60): string
    {
        if ($media->disk === 's3') {
            // Verify S3 is properly configured before attempting
            if (config('filesystems.disks.s3.bucket')) {
                return Storage::disk('s3')->temporaryUrl(
                    $media->file_path,
                    now()->addMinutes($expirationMinutes),
                );
            }

            // S3 not configured — fall back to public disk if file exists there
            if (Storage::disk('public')->exists($media->file_path)) {
                return Storage::disk('public')->url($media->file_path);
            }

            throw new \RuntimeException('Le fichier est stocké sur S3 mais S3 n\'est pas configuré.');
        }

        return Storage::disk($media->disk)->url($media->file_path);
    }

    /**
     * Get the file contents of a media item for direct download.
     */
    public function getFileContents(Media $media): ?string
    {
        $disk = $media->disk;

        // If disk is s3 but not configured, try public
        if ($disk === 's3' && ! config('filesystems.disks.s3.bucket')) {
            $disk = 'public';
        }

        if (Storage::disk($disk)->exists($media->file_path)) {
            return Storage::disk($disk)->get($media->file_path);
        }

        return null;
    }

    public function recalculateUsage(Organization $org): void
    {
        $totalBytes = Media::where('organization_id', $org->id)->sum('size_bytes');

        StorageUsage::updateOrCreate(
            ['organization_id' => $org->id],
            ['used_bytes' => $totalBytes, 'updated_at' => now()],
        );
    }

    /**
     * Resolve the correct MIME type for a file.
     * PHP's finfo detects .m4a (AAC audio in MP4 container) as video/mp4.
     * We use the client MIME for audio files and fall back to server detection.
     */
    protected function resolveMimeType(UploadedFile $file, string $collection): string
    {
        if ($collection === 'audio') {
            $ext = strtolower($file->getClientOriginalExtension());
            $audioMimes = [
                'm4a' => 'audio/mp4',
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'ogg' => 'audio/ogg',
                'webm' => 'audio/webm',
                'aac' => 'audio/aac',
            ];
            if (isset($audioMimes[$ext])) {
                return $audioMimes[$ext];
            }
        }

        return $file->getMimeType() ?: $file->getClientMimeType();
    }

    protected function incrementUsage(Organization $org, int $bytes): void
    {
        StorageUsage::updateOrCreate(
            ['organization_id' => $org->id],
            ['updated_at' => now()],
        );

        StorageUsage::where('organization_id', $org->id)
            ->increment('used_bytes', $bytes);
    }
}
