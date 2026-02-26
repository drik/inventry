<?php

namespace App\Http\Controllers\Api;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Models\AiRecognitionLog;
use App\Models\Asset;
use App\Models\AssetImage;
use App\Models\AssetTag;
use App\Models\AssetTagValue;
use App\Services\AiVisionService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    public function __construct(
        protected AiVisionService $aiVisionService,
        protected PlanLimitService $planLimitService,
    ) {}

    /**
     * List assets with search, filters, and pagination.
     * GET /api/assets
     */
    public function index(Request $request): JsonResponse
    {
        $org = $request->user()->organization;

        $query = Asset::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->with(['category', 'location', 'manufacturer', 'assetModel', 'primaryImage', 'tagValues.tag']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('asset_code', 'LIKE', "%{$search}%")
                    ->orWhereHas('tagValues', fn ($q) => $q->where('value', 'LIKE', "%{$search}%"));
            });
        }

        // Filters
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($locationId = $request->input('location_id')) {
            $query->where('location_id', $locationId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $assets = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'data' => $assets->map(fn (Asset $a) => $this->formatAsset($a)),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    /**
     * Show asset detail.
     * GET /api/assets/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $org = $request->user()->organization;

        $asset = Asset::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->with([
                'category', 'location', 'department', 'manufacturer',
                'assetModel', 'supplier', 'images', 'tagValues.tag',
                'currentAssignment.assignee',
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->formatAssetDetailed($asset),
        ]);
    }

    /**
     * Create an asset.
     * POST /api/assets
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|string',
            'location_id' => 'required|string',
            'manufacturer_id' => 'nullable|string',
            'model_id' => 'nullable|string',
            'status' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'tag_values' => 'nullable|array',
            'tag_values.*.asset_tag_id' => 'required_with:tag_values|string',
            'tag_values.*.value' => 'required_with:tag_values|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ]);

        $org = $request->user()->organization;

        // Validate uniqueness of tag values before creating the asset
        if ($request->has('tag_values')) {
            $this->validateUniqueTagValues($request->input('tag_values'), $org->id);
        }

        $asset = Asset::create([
            'organization_id' => $org->id,
            'name' => $request->input('name'),
            'category_id' => $request->input('category_id'),
            'location_id' => $request->input('location_id'),
            'manufacturer_id' => $request->input('manufacturer_id'),
            'model_id' => $request->input('model_id'),
            'status' => $request->input('status', 'available'),
            'purchase_date' => $request->input('purchase_date'),
            'purchase_cost' => $request->input('purchase_cost'),
            'notes' => $request->input('notes'),
        ]);

        // Create tag values
        if ($request->has('tag_values')) {
            foreach ($request->input('tag_values') as $tagValue) {
                if (! empty($tagValue['value'])) {
                    AssetTagValue::create([
                        'organization_id' => $org->id,
                        'asset_id' => $asset->id,
                        'asset_tag_id' => $tagValue['asset_tag_id'],
                        'value' => $tagValue['value'],
                        'encoding_mode' => $tagValue['encoding_mode'] ?? null,
                    ]);
                }
            }
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('asset-images', 'public');
            AssetImage::create([
                'organization_id' => $org->id,
                'asset_id' => $asset->id,
                'file_path' => $path,
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        }

        // Confirm suggested entities (implicit approval)
        $this->confirmSuggestedEntities($asset);

        $asset->load(['category', 'location', 'manufacturer', 'assetModel', 'primaryImage', 'tagValues.tag']);

        return response()->json([
            'data' => $this->formatAsset($asset),
        ], 201);
    }

    /**
     * Update an asset.
     * PUT /api/assets/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|string',
            'location_id' => 'sometimes|string',
            'manufacturer_id' => 'nullable|string',
            'model_id' => 'nullable|string',
            'status' => 'sometimes|string',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'tag_values' => 'nullable|array',
            'tag_values.*.asset_tag_id' => 'required_with:tag_values|string',
            'tag_values.*.value' => 'required_with:tag_values|string',
        ]);

        $org = $request->user()->organization;
        $asset = Asset::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->findOrFail($id);

        // Validate uniqueness of tag values (excluding current asset)
        if ($request->has('tag_values')) {
            $this->validateUniqueTagValues($request->input('tag_values'), $org->id, $asset->id);
        }

        $asset->update($request->only([
            'name', 'category_id', 'location_id', 'manufacturer_id',
            'model_id', 'status', 'purchase_date', 'purchase_cost', 'notes',
        ]));

        // Sync tag values
        if ($request->has('tag_values')) {
            $asset->tagValues()->delete();
            foreach ($request->input('tag_values') as $tagValue) {
                if (! empty($tagValue['value'])) {
                    AssetTagValue::create([
                        'organization_id' => $org->id,
                        'asset_id' => $asset->id,
                        'asset_tag_id' => $tagValue['asset_tag_id'],
                        'value' => $tagValue['value'],
                        'encoding_mode' => $tagValue['encoding_mode'] ?? null,
                    ]);
                }
            }
        }

        $asset->load([
            'category', 'location', 'department', 'manufacturer',
            'assetModel', 'supplier', 'images', 'tagValues.tag',
            'currentAssignment.assignee',
        ]);

        return response()->json([
            'data' => $this->formatAssetDetailed($asset),
        ]);
    }

    /**
     * Soft delete an asset.
     * DELETE /api/assets/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $org = $request->user()->organization;
        $asset = Asset::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->findOrFail($id);

        $asset->delete();

        return response()->json([
            'message' => 'Asset supprimé.',
        ]);
    }

    /**
     * Extract asset info from a photo using AI (without creating the asset).
     * POST /api/assets/ai-extract
     */
    public function aiExtract(Request $request): JsonResponse
    {
        if (! config('ai-vision.enabled')) {
            return response()->json(['message' => "La fonctionnalité IA Vision n'est pas activée."], 503);
        }

        $maxSize = config('ai-vision.limits.max_image_size_kb', 2048);

        $request->validate([
            'photos' => 'required_without:photo|array|min:1|max:5',
            'photos.*' => "image|mimes:jpeg,jpg,png|max:{$maxSize}",
            'photo' => "required_without:photos|image|mimes:jpeg,jpg,png|max:{$maxSize}",
        ]);

        $org = $request->user()->organization;

        // Check monthly quota (daily is checked by middleware)
        if (! $this->planLimitService->canCreate($org, PlanFeature::MaxAiRequestsMonthly)) {
            return response()->json([
                'message' => $this->planLimitService->getLimitReachedMessage(PlanFeature::MaxAiRequestsMonthly, $org),
                'error' => 'plan_limit_reached',
                'feature' => 'max_ai_requests_monthly',
            ], 403);
        }

        // Support both 'photos' (array) and 'photo' (single) for backward compat
        $files = $request->file('photos') ?? [$request->file('photo')];

        $storagePaths = [];
        $absolutePaths = [];
        foreach ($files as $file) {
            $path = $this->aiVisionService->storeCapturedPhoto($org, $file);
            $storagePaths[] = $path;
            $absolutePaths[] = Storage::disk('public')->path($path);
        }

        // Extract asset info
        $result = $this->aiVisionService->extractAssetInfo(
            imagePaths: $absolutePaths,
            organization: $org,
            storagePaths: $storagePaths,
        );

        $usage = $this->aiVisionService->getUsageStats($org);

        return response()->json([
            'recognition_log_id' => $result['recognition_log_id'],
            'extraction' => $result['extraction']->toArray(),
            'resolved_ids' => $result['resolved_ids'],
            'image_paths' => $result['image_paths'],
            'usage' => $usage,
        ]);
    }

    /**
     * Extract asset info from a photo using AI and create the asset.
     * POST /api/assets/ai-create
     */
    public function aiCreate(Request $request): JsonResponse
    {
        if (! config('ai-vision.enabled')) {
            return response()->json(['message' => "La fonctionnalité IA Vision n'est pas activée."], 503);
        }

        $maxSize = config('ai-vision.limits.max_image_size_kb', 2048);

        $request->validate([
            'photos' => 'required_without:photo|array|min:1|max:5',
            'photos.*' => "image|mimes:jpeg,jpg,png|max:{$maxSize}",
            'photo' => "required_without:photos|image|mimes:jpeg,jpg,png|max:{$maxSize}",
            'location_id' => 'nullable|string',
            'name' => 'nullable|string|max:255',
            'category_id' => 'nullable|string',
            'manufacturer_id' => 'nullable|string',
            'model_id' => 'nullable|string',
            'supplier_id' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $org = $request->user()->organization;

        // Check monthly quota
        if (! $this->planLimitService->canCreate($org, PlanFeature::MaxAiRequestsMonthly)) {
            return response()->json([
                'message' => $this->planLimitService->getLimitReachedMessage(PlanFeature::MaxAiRequestsMonthly, $org),
                'error' => 'plan_limit_reached',
                'feature' => 'max_ai_requests_monthly',
            ], 403);
        }

        // Support both 'photos' (array) and 'photo' (single) for backward compat
        $files = $request->file('photos') ?? [$request->file('photo')];

        $storagePaths = [];
        $absolutePaths = [];
        foreach ($files as $file) {
            $path = $this->aiVisionService->storeCapturedPhoto($org, $file);
            $storagePaths[] = $path;
            $absolutePaths[] = Storage::disk('public')->path($path);
        }

        $result = $this->aiVisionService->extractAssetInfo(
            imagePaths: $absolutePaths,
            organization: $org,
            storagePaths: $storagePaths,
        );

        $extraction = $result['extraction'];
        $resolvedIds = $result['resolved_ids'];

        // Build tag values from AI extraction for validation
        $aiTagValues = [];
        if ($extraction->serialNumber || $extraction->sku) {
            $systemTags = AssetTag::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->where('is_system', true)
                ->get()
                ->keyBy('name');

            if ($extraction->serialNumber && $systemTags->has('Serial Number')) {
                $aiTagValues[] = [
                    'asset_tag_id' => $systemTags->get('Serial Number')->id,
                    'value' => $extraction->serialNumber,
                ];
            }

            if ($extraction->sku && $systemTags->has('SKU')) {
                $aiTagValues[] = [
                    'asset_tag_id' => $systemTags->get('SKU')->id,
                    'value' => $extraction->sku,
                ];
            }
        }

        // Validate uniqueness of AI-extracted tag values before creating the asset
        if (! empty($aiTagValues)) {
            $this->validateUniqueTagValues($aiTagValues, $org->id);
        }

        // Resolve location_id: user override > AI resolved > error
        $locationId = $request->input('location_id') ?? $resolvedIds['location_id'] ?? null;
        if (! $locationId) {
            throw ValidationException::withMessages([
                'location_id' => "Un emplacement est requis. Ni l'utilisateur ni l'IA n'ont pu en déterminer un.",
            ]);
        }

        // Create asset — user overrides take priority, then AI, then defaults
        $asset = Asset::create([
            'organization_id' => $org->id,
            'name' => $request->input('name') ?? $extraction->suggestedName ?? 'Asset sans nom',
            'category_id' => $request->input('category_id') ?? $resolvedIds['category_id'],
            'location_id' => $locationId,
            'manufacturer_id' => $request->input('manufacturer_id') ?? $resolvedIds['manufacturer_id'],
            'model_id' => $request->input('model_id') ?? $resolvedIds['model_id'],
            'supplier_id' => $request->input('supplier_id') ?? $resolvedIds['supplier_id'] ?? null,
            'status' => 'available',
            'purchase_cost' => $extraction->purchaseCost,
            'purchase_date' => $extraction->purchaseDate,
            'warranty_expiry' => $extraction->warrantyExpiry,
            'notes' => $request->input('notes') ?? $extraction->suggestedDescription,
        ]);

        // Create tag values for serial_number and sku
        foreach ($aiTagValues as $tagValue) {
            AssetTagValue::create([
                'organization_id' => $org->id,
                'asset_id' => $asset->id,
                'asset_tag_id' => $tagValue['asset_tag_id'],
                'value' => $tagValue['value'],
            ]);
        }

        // Create images from captured photos (first = primary)
        foreach ($storagePaths as $index => $imagePath) {
            AssetImage::create([
                'organization_id' => $org->id,
                'asset_id' => $asset->id,
                'file_path' => $imagePath,
                'caption' => 'Photo IA',
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }

        // Update recognition log
        AiRecognitionLog::withoutGlobalScopes()
            ->where('id', $result['recognition_log_id'])
            ->update([
                'selected_asset_id' => $asset->id,
                'selected_action' => 'created',
            ]);

        // Confirm suggested entities (implicit approval)
        $this->confirmSuggestedEntities($asset);

        $asset->load(['category', 'location', 'manufacturer', 'assetModel', 'primaryImage', 'tagValues.tag']);
        $usage = $this->aiVisionService->getUsageStats($org);

        return response()->json([
            'data' => $this->formatAssetDetailed($asset),
            'extraction' => $extraction->toArray(),
            'resolved_ids' => $resolvedIds,
            'recognition_log_id' => $result['recognition_log_id'],
            'usage' => $usage,
        ], 201);
    }

    /**
     * Validate that tag values marked as unique don't have duplicates in the organization.
     *
     * @throws ValidationException
     */
    private function validateUniqueTagValues(array $tagValues, string $orgId, ?string $excludeAssetId = null): void
    {
        $errors = [];

        // Get all unique tag IDs from the submitted values
        $tagIds = collect($tagValues)
            ->filter(fn ($tv) => ! empty($tv['value']))
            ->pluck('asset_tag_id')
            ->unique()
            ->toArray();

        if (empty($tagIds)) {
            return;
        }

        // Fetch tags that are marked as unique
        $uniqueTags = AssetTag::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->whereIn('id', $tagIds)
            ->where('is_unique', true)
            ->get()
            ->keyBy('id');

        if ($uniqueTags->isEmpty()) {
            return;
        }

        foreach ($tagValues as $index => $tagValue) {
            if (empty($tagValue['value'])) {
                continue;
            }

            $tag = $uniqueTags->get($tagValue['asset_tag_id']);
            if (! $tag) {
                continue;
            }

            $query = AssetTagValue::where('organization_id', $orgId)
                ->where('asset_tag_id', $tag->id)
                ->where('value', $tagValue['value']);

            if ($excludeAssetId) {
                $query->where('asset_id', '!=', $excludeAssetId);
            }

            if ($query->exists()) {
                $errors["tag_values.{$index}.value"] = "La valeur \"{$tagValue['value']}\" existe déjà pour le tag \"{$tag->name}\". Les valeurs de ce tag doivent être uniques.";
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Confirm suggested entities referenced by the asset.
     */
    private function confirmSuggestedEntities(Asset $asset): void
    {
        foreach (['category', 'manufacturer', 'assetModel', 'location', 'supplier'] as $relation) {
            $entity = $asset->$relation;
            if ($entity && $entity->suggested === true) {
                $entity->update(['suggested' => false]);
            }
        }
    }

    /**
     * Format asset for list responses.
     */
    private function formatAsset(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'name' => $asset->name,
            'category_name' => $asset->category?->name,
            'location_name' => $asset->location?->name,
            'manufacturer_name' => $asset->manufacturer?->name,
            'model_name' => $asset->assetModel?->name,
            'status' => $asset->status?->value ?? $asset->status,
            'primary_image_url' => $asset->primaryImage?->file_path
                ? asset('storage/' . $asset->primaryImage->file_path)
                : null,
            'tag_values' => $asset->tagValues->map(fn ($tv) => [
                'tag_name' => $tv->tag?->name,
                'value' => $tv->value,
            ]),
            'created_at' => $asset->created_at->toIso8601String(),
        ];
    }

    /**
     * Format asset for detail responses.
     */
    private function formatAssetDetailed(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'name' => $asset->name,
            'category_id' => $asset->category_id,
            'category_name' => $asset->category?->name,
            'location_id' => $asset->location_id,
            'location_name' => $asset->location?->name,
            'department_id' => $asset->department_id,
            'department_name' => $asset->department?->name,
            'manufacturer_id' => $asset->manufacturer_id,
            'manufacturer_name' => $asset->manufacturer?->name,
            'model_id' => $asset->model_id,
            'model_name' => $asset->assetModel?->name,
            'supplier_id' => $asset->supplier_id,
            'supplier_name' => $asset->supplier?->name,
            'status' => $asset->status?->value ?? $asset->status,
            'purchase_date' => $asset->purchase_date?->toDateString(),
            'purchase_cost' => $asset->purchase_cost,
            'warranty_expiry' => $asset->warranty_expiry?->toDateString(),
            'notes' => $asset->notes,
            'primary_image_url' => $asset->primaryImage?->file_path
                ? asset('storage/' . $asset->primaryImage->file_path)
                : null,
            'images' => $asset->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => asset('storage/' . $img->file_path),
                'caption' => $img->caption,
                'is_primary' => $img->is_primary,
            ]),
            'tag_values' => $asset->tagValues->map(fn ($tv) => [
                'id' => $tv->id,
                'asset_tag_id' => $tv->asset_tag_id,
                'tag_name' => $tv->tag?->name,
                'value' => $tv->value,
                'encoding_mode' => $tv->encoding_mode?->value ?? null,
            ]),
            'current_assignment' => $asset->currentAssignment ? [
                'assignee_name' => $asset->currentAssignment->assignee?->name,
                'assigned_at' => $asset->currentAssignment->assigned_at?->toIso8601String(),
                'expected_return_at' => $asset->currentAssignment->expected_return_at?->toDateString(),
            ] : null,
            'created_at' => $asset->created_at->toIso8601String(),
            'updated_at' => $asset->updated_at->toIso8601String(),
        ];
    }
}
