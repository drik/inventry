<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Media;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(
        protected StorageService $storageService,
    ) {}

    /**
     * Upload a document to an asset.
     */
    public function upload(Request $request, string $assetId): JsonResponse
    {
        $allowedMimes = implode(',', config('media.allowed_document_mimes', []));
        $request->validate([
            'file' => "required|file|mimes:{$allowedMimes}|max:" . (config('media.max_upload_size_mb', 50) * 1024),
        ]);

        $org = $request->user()->organization;

        $asset = Asset::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->findOrFail($assetId);

        $media = $this->storageService->upload(
            $org,
            $request->file('file'),
            'documents',
            $asset,
            $request->user()->id,
        );

        return response()->json([
            'media' => [
                'id' => $media->id,
                'collection' => $media->collection,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size_bytes' => $media->size_bytes,
                'url' => $media->url,
                'created_at' => $media->created_at->toIso8601String(),
            ],
            'storage' => $this->storageService->getUsageStats($org),
        ], 201);
    }

    /**
     * List documents for an asset.
     */
    public function index(Request $request, string $assetId): JsonResponse
    {
        $org = $request->user()->organization;

        $asset = Asset::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->findOrFail($assetId);

        $documents = $asset->documents()->orderByDesc('created_at')->get();

        return response()->json([
            'documents' => $documents->map(fn (Media $m) => [
                'id' => $m->id,
                'file_name' => $m->file_name,
                'mime_type' => $m->mime_type,
                'size_bytes' => $m->size_bytes,
                'human_size' => $m->human_size,
                'url' => $m->url,
                'uploaded_by' => $m->uploaded_by,
                'created_at' => $m->created_at->toIso8601String(),
            ]),
        ]);
    }
}
