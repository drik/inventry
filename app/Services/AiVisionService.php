<?php

namespace App\Services;

use App\DTOs\AiIdentificationResult;
use App\DTOs\AiMatchResult;
use App\DTOs\AiVerificationResult;
use App\Enums\PlanFeature;
use App\Models\AiRecognitionLog;
use App\Models\AiUsageLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Services\AiVision\VisionProviderInterface;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class AiVisionService
{
    public function __construct(
        protected AiVision\GeminiVisionProvider $geminiProvider,
        protected AiVision\OpenAiVisionProvider $openAiProvider,
        protected PlanLimitService $planLimitService,
    ) {}

    /**
     * Identify an asset from a photo and find matches among known assets.
     */
    public function analyzePhoto(
        string $imagePath,
        Organization $organization,
        ?string $locationId = null,
        ?string $taskId = null,
    ): array {
        $startTime = microtime(true);
        $usedFallback = false;

        // Prepare the captured image
        $capturedBase64 = $this->prepareImage($imagePath);

        // Select candidate assets for matching
        $candidates = $this->selectCandidates($organization, $locationId, $taskId);
        $candidateMap = []; // ref_key => asset_id

        // Prepare candidate images
        $images = ['captured' => $capturedBase64];
        foreach ($candidates as $index => $candidate) {
            $refKey = 'ref_'.($index + 1);
            $candidateMap[$refKey] = $candidate['asset_id'];

            if ($candidate['image_base64']) {
                $images[$refKey] = $candidate['image_base64'];
            }
        }

        // Build the prompt
        $categories = AssetCategory::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->pluck('name')
            ->toArray();

        $prompt = $this->buildIdentifyPrompt($categories, $candidates);
        $systemPrompt = $this->getSystemPrompt();

        // Select provider based on plan
        $provider = $this->getProvider($organization);

        try {
            $apiResult = $provider->analyze($images, $prompt, ['system' => $systemPrompt]);
        } catch (\Exception $e) {
            // If primary provider fails → fallback (except Freemium)
            $plan = $this->planLimitService->getEffectivePlan($organization);
            if ($plan->slug === 'freemium') {
                throw $e;
            }
            $provider = $this->getFallbackProvider($organization);
            $apiResult = $provider->analyze($images, $prompt, ['system' => $systemPrompt]);
            $usedFallback = true;
        }

        $response = $apiResult['response'];

        // Check if fallback needed based on confidence (Basic/Pro)
        if (! $usedFallback && $this->shouldFallback($organization, $response)) {
            $fallbackProvider = $this->getFallbackProvider($organization);

            try {
                $fallbackResult = $fallbackProvider->analyze($images, $prompt, ['system' => $systemPrompt]);
                $response = $fallbackResult['response'];
                $apiResult['prompt_tokens'] = ($apiResult['prompt_tokens'] ?? 0) + ($fallbackResult['prompt_tokens'] ?? 0);
                $apiResult['completion_tokens'] = ($apiResult['completion_tokens'] ?? 0) + ($fallbackResult['completion_tokens'] ?? 0);
                $provider = $fallbackProvider;
                $usedFallback = true;
            } catch (\Exception $e) {
                // Keep original result if fallback fails
            }
        }

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        // Parse results
        $identification = AiIdentificationResult::fromAiResponse($response['identification'] ?? []);

        $matches = [];
        foreach ($response['matches'] ?? [] as $matchData) {
            if (isset($candidateMap[$matchData['reference_key'] ?? ''])) {
                $matches[] = AiMatchResult::fromAiResponse($matchData, $candidateMap);
            }
        }

        // Sort matches by confidence descending
        usort($matches, fn ($a, $b) => $b->confidence <=> $a->confidence);

        $matchedAssetIds = array_map(fn ($m) => $m->assetId, $matches);

        // Estimate cost
        $estimatedCost = $this->estimateCost(
            $provider->getProviderName(),
            $apiResult['prompt_tokens'] ?? 0,
            $apiResult['completion_tokens'] ?? 0,
        );

        // Double logging
        $log = $this->logRecognition(
            organization: $organization,
            taskId: $taskId,
            imagePath: $imagePath,
            useCase: 'identify',
            provider: $provider,
            usedFallback: $usedFallback,
            response: $response,
            matchedAssetIds: $matchedAssetIds,
            promptTokens: $apiResult['prompt_tokens'],
            completionTokens: $apiResult['completion_tokens'],
            estimatedCost: $estimatedCost,
            latencyMs: $latencyMs,
        );

        return [
            'recognition_log_id' => $log->id,
            'identification' => $identification,
            'matches' => $matches,
            'has_strong_match' => count($matches) > 0 && $matches[0]->confidence >= 0.7,
            'provider' => $provider->getProviderName(),
            'used_fallback' => $usedFallback,
        ];
    }

    /**
     * Verify that a captured photo matches a specific asset.
     */
    public function verifyAssetIdentity(
        string $capturedImagePath,
        Asset $asset,
        Organization $organization,
        ?string $taskId = null,
    ): array {
        $startTime = microtime(true);
        $usedFallback = false;

        $capturedBase64 = $this->prepareImage($capturedImagePath);

        // Get asset's primary image
        $assetImage = $asset->primaryImage;
        if (! $assetImage || ! Storage::exists($assetImage->file_path)) {
            return [
                'recognition_log_id' => null,
                'verification' => new AiVerificationResult(
                    isMatch: false,
                    confidence: 0,
                    reasoning: "L'asset n'a pas d'image de référence pour la comparaison.",
                    discrepancies: ['no_reference_image'],
                ),
            ];
        }

        $referenceBase64 = $this->prepareImage(Storage::path($assetImage->file_path));

        $images = [
            'captured' => $capturedBase64,
            'reference' => $referenceBase64,
        ];

        $prompt = $this->buildVerifyPrompt($asset);
        $systemPrompt = $this->getSystemPrompt();

        $provider = $this->getProvider($organization);

        try {
            $apiResult = $provider->analyze($images, $prompt, ['system' => $systemPrompt]);
        } catch (\Exception $e) {
            $plan = $this->planLimitService->getEffectivePlan($organization);
            if ($plan->slug === 'freemium') {
                throw $e;
            }
            $provider = $this->getFallbackProvider($organization);
            $apiResult = $provider->analyze($images, $prompt, ['system' => $systemPrompt]);
            $usedFallback = true;
        }

        $response = $apiResult['response'];
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        $verification = AiVerificationResult::fromAiResponse($response);

        $estimatedCost = $this->estimateCost(
            $provider->getProviderName(),
            $apiResult['prompt_tokens'] ?? 0,
            $apiResult['completion_tokens'] ?? 0,
        );

        $log = $this->logRecognition(
            organization: $organization,
            taskId: $taskId,
            imagePath: $capturedImagePath,
            useCase: 'verify',
            provider: $provider,
            usedFallback: $usedFallback,
            response: $response,
            matchedAssetIds: [$asset->id],
            promptTokens: $apiResult['prompt_tokens'],
            completionTokens: $apiResult['completion_tokens'],
            estimatedCost: $estimatedCost,
            latencyMs: $latencyMs,
        );

        return [
            'recognition_log_id' => $log->id,
            'verification' => $verification,
            'provider' => $provider->getProviderName(),
            'used_fallback' => $usedFallback,
        ];
    }

    /**
     * Check if the organization can make an AI request (daily AND monthly quotas).
     */
    public function canMakeRequest(Organization $org): bool
    {
        return $this->planLimitService->canCreate($org, PlanFeature::MaxAiRequestsDaily)
            && $this->planLimitService->canCreate($org, PlanFeature::MaxAiRequestsMonthly);
    }

    /**
     * Get usage stats for the organization.
     */
    public function getUsageStats(Organization $org): array
    {
        return [
            'daily' => $this->planLimitService->getUsageStats($org, PlanFeature::MaxAiRequestsDaily),
            'monthly' => $this->planLimitService->getUsageStats($org, PlanFeature::MaxAiRequestsMonthly),
        ];
    }

    /**
     * Select provider based on the organization's plan.
     */
    protected function getProvider(Organization $org): VisionProviderInterface
    {
        $plan = $this->planLimitService->getEffectivePlan($org);

        // Premium → GPT-4o directly
        if ($plan->slug === 'premium') {
            return $this->openAiProvider;
        }

        // Freemium, Basic, Pro → Gemini Flash
        return $this->geminiProvider;
    }

    /**
     * Determine if a fallback provider should be used based on confidence.
     */
    protected function shouldFallback(Organization $org, array $response): bool
    {
        $plan = $this->planLimitService->getEffectivePlan($org);

        // Freemium → never fallback
        if ($plan->slug === 'freemium') {
            return false;
        }

        // Premium → fallback only on error (handled by try/catch)
        if ($plan->slug === 'premium') {
            return false;
        }

        // Basic / Pro → fallback if low confidence
        $confidence = $response['identification']['confidence'] ?? 1;

        return $confidence < config('ai-vision.fallback_confidence_threshold', 0.5);
    }

    /**
     * Get the fallback provider for the organization's plan.
     */
    protected function getFallbackProvider(Organization $org): VisionProviderInterface
    {
        $plan = $this->planLimitService->getEffectivePlan($org);

        // Premium: fallback = Gemini
        if ($plan->slug === 'premium') {
            return $this->geminiProvider;
        }

        // Basic / Pro: fallback = GPT-4o
        return $this->openAiProvider;
    }

    /**
     * Select candidate assets for image matching.
     */
    protected function selectCandidates(Organization $org, ?string $locationId, ?string $taskId): array
    {
        $maxCandidates = config('ai-vision.limits.max_reference_images', 8);
        $candidates = collect();

        // Priority 1: Expected items in the current task at the same location
        if ($taskId) {
            $task = \App\Models\InventoryTask::withoutGlobalScopes()->find($taskId);
            if ($task) {
                $expectedAssetIds = InventoryItem::withoutGlobalScopes()
                    ->where('session_id', $task->session_id)
                    ->where('status', 'expected')
                    ->pluck('asset_id')
                    ->filter();

                $expectedAssets = Asset::withoutGlobalScopes()
                    ->where('organization_id', $org->id)
                    ->whereIn('id', $expectedAssetIds)
                    ->whereHas('primaryImage')
                    ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
                    ->with(['primaryImage', 'category', 'location'])
                    ->limit($maxCandidates)
                    ->get();

                foreach ($expectedAssets as $asset) {
                    $candidates->push($this->assetToCandidate($asset));
                }
            }
        }

        // Priority 2: Other assets at the same location with image
        if ($candidates->count() < $maxCandidates && $locationId) {
            $existing = $candidates->pluck('asset_id')->toArray();
            $locationAssets = Asset::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->where('location_id', $locationId)
                ->whereHas('primaryImage')
                ->whereNotIn('id', $existing)
                ->with(['primaryImage', 'category', 'location'])
                ->limit($maxCandidates - $candidates->count())
                ->get();

            foreach ($locationAssets as $asset) {
                $candidates->push($this->assetToCandidate($asset));
            }
        }

        // Priority 3: Any org asset with image (if still under limit)
        if ($candidates->count() < $maxCandidates) {
            $existing = $candidates->pluck('asset_id')->toArray();
            $orgAssets = Asset::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->whereHas('primaryImage')
                ->whereNotIn('id', $existing)
                ->with(['primaryImage', 'category', 'location'])
                ->limit($maxCandidates - $candidates->count())
                ->get();

            foreach ($orgAssets as $asset) {
                $candidates->push($this->assetToCandidate($asset));
            }
        }

        return $candidates->toArray();
    }

    /**
     * Convert an asset to a candidate array for LLM comparison.
     */
    protected function assetToCandidate(Asset $asset): array
    {
        $imagePath = $asset->primaryImage?->file_path;
        $imageBase64 = null;

        if ($imagePath && Storage::exists($imagePath)) {
            $imageBase64 = $this->prepareImageFromStorage($imagePath);
        }

        return [
            'asset_id' => $asset->id,
            'asset_name' => $asset->name,
            'asset_code' => $asset->asset_code,
            'category_name' => $asset->category?->name,
            'location_name' => $asset->location?->name,
            'image_base64' => $imageBase64,
        ];
    }

    /**
     * Prepare an image for the API: resize to max 1024px, compress to JPEG 85%.
     */
    protected function prepareImage(string $absolutePath): string
    {
        $image = Image::read($absolutePath);

        // Resize to max 1024px on longest side
        $image->scaleDown(1024, 1024);

        // Encode to JPEG 85%
        $encoded = $image->toJpeg(85);

        return base64_encode((string) $encoded);
    }

    /**
     * Prepare an image from storage path.
     */
    protected function prepareImageFromStorage(string $storagePath): string
    {
        return $this->prepareImage(Storage::path($storagePath));
    }

    /**
     * Get the system prompt for the LLM.
     */
    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant spécialisé dans l'identification d'actifs physiques pour un inventaire d'entreprise. Tu analyses des photos prises par des employés pendant un inventaire physique. Tu dois identifier l'objet et le comparer aux images de référence fournies.

Règles :
- Concentre-toi uniquement sur l'objet principal de la photo
- Ignore l'arrière-plan et les éléments secondaires
- Réponds UNIQUEMENT en JSON valide
- Les scores de confiance vont de 0.0 (aucune confiance) à 1.0 (certitude absolue)
PROMPT;
    }

    /**
     * Build the identification + matching prompt.
     */
    protected function buildIdentifyPrompt(array $categories, array $candidates): string
    {
        $categoryList = implode(', ', $categories);

        $prompt = <<<PROMPT
Les catégories d'actifs de cette organisation sont : {$categoryList}

Analyse la photo capturée (image "captured") et retourne un JSON avec :
1. "identification" : un objet avec les champs "suggested_category" (parmi les catégories listées), "suggested_brand" (marque si visible, sinon null), "suggested_model" (modèle si visible, sinon null), "detected_text" (tableau des textes détectés : numéros de série, références, étiquettes), "confidence" (score de 0.0 à 1.0), "description" (description courte de l'objet)
PROMPT;

        if (count($candidates) > 0) {
            $prompt .= "\n2. \"matches\" : un tableau comparant la photo capturée aux images de référence. Pour chaque image de référence, indique :\n";
            $prompt .= "   - \"reference_key\" : la clé de l'image (ref_1, ref_2, etc.)\n";
            $prompt .= "   - \"confidence\" : score de correspondance de 0.0 à 1.0\n";
            $prompt .= "   - \"reasoning\" : explication courte de la correspondance ou non\n\n";
            $prompt .= "Images de référence :\n";

            foreach ($candidates as $index => $candidate) {
                $refKey = 'ref_'.($index + 1);
                $prompt .= "- {$refKey} ({$candidate['asset_code']}, \"{$candidate['asset_name']}\", {$candidate['category_name']})\n";
            }
        } else {
            $prompt .= "\n2. \"matches\" : tableau vide [] (aucune image de référence disponible)\n";
        }

        return $prompt;
    }

    /**
     * Build the verification prompt.
     */
    protected function buildVerifyPrompt(Asset $asset): string
    {
        return <<<PROMPT
Compare la photo capturée (image "captured") avec l'image de référence (image "reference") de l'asset "{$asset->name}" (code: {$asset->asset_code}, catégorie: {$asset->category?->name}).

Retourne un JSON avec :
- "is_match" : true si les deux images montrent le même objet physique, false sinon
- "confidence" : score de confiance de 0.0 à 1.0
- "reasoning" : explication détaillée de ta conclusion
- "discrepancies" : tableau des différences observées (vide si correspondance parfaite)
PROMPT;
    }

    /**
     * Log the recognition to both ai_usage_logs and ai_recognition_logs.
     */
    protected function logRecognition(
        Organization $organization,
        ?string $taskId,
        string $imagePath,
        string $useCase,
        VisionProviderInterface $provider,
        bool $usedFallback,
        array $response,
        ?array $matchedAssetIds,
        ?int $promptTokens,
        ?int $completionTokens,
        ?float $estimatedCost,
        int $latencyMs,
    ): AiRecognitionLog {
        // 1. Usage log (for plan quota counting)
        AiUsageLog::create([
            'organization_id' => $organization->id,
            'user_id' => auth()->id(),
            'feature' => 'ai_vision',
            'tokens_used' => ($promptTokens ?? 0) + ($completionTokens ?? 0),
        ]);

        // 2. Detailed recognition log
        return AiRecognitionLog::create([
            'organization_id' => $organization->id,
            'task_id' => $taskId,
            'user_id' => auth()->id(),
            'captured_image_path' => $imagePath,
            'use_case' => $useCase,
            'provider' => $provider->getProviderName(),
            'model' => $provider->getModelName(),
            'used_fallback' => $usedFallback,
            'ai_response' => $response,
            'matched_asset_ids' => $matchedAssetIds,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'estimated_cost_usd' => $estimatedCost,
            'latency_ms' => $latencyMs,
        ]);
    }

    /**
     * Estimate cost based on provider and token usage.
     */
    protected function estimateCost(string $providerName, int $promptTokens, int $completionTokens): float
    {
        // Approximate pricing per 1M tokens
        $pricing = match ($providerName) {
            'gemini' => ['input' => 0.075, 'output' => 0.30],   // Gemini 2.5 Flash
            'openai' => ['input' => 2.50, 'output' => 10.00],   // GPT-4o
            default => ['input' => 0, 'output' => 0],
        };

        return ($promptTokens * $pricing['input'] / 1_000_000)
             + ($completionTokens * $pricing['output'] / 1_000_000);
    }

    /**
     * Store a captured photo and return its storage path.
     */
    public function storeCapturedPhoto(Organization $org, $uploadedFile): string
    {
        $date = now()->format('Y-m-d');
        $directory = "ai-captures/{$org->id}/{$date}";

        return $uploadedFile->store($directory);
    }
}
