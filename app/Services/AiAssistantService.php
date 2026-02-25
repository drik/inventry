<?php

namespace App\Services;

use App\DTOs\AiAssistantResult;
use App\Enums\PlanFeature;
use App\Models\AiUsageLog;
use App\Models\Organization;
use App\Services\AiProviders\AiProviderInterface;
use App\Services\AiProviders\GeminiProvider;
use App\Services\AiProviders\OpenAiProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiAssistantService
{
    public function __construct(
        protected GeminiProvider $geminiProvider,
        protected OpenAiProvider $openAiProvider,
        protected PlanLimitService $planLimitService,
    ) {}

    public function rephraseText(string $text, Organization $org, string $userId): AiAssistantResult
    {
        $prompt = "Tu es un assistant d'inventaire professionnel. Reformule la note suivante de manière "
            . "claire, concise et professionnelle, en conservant toutes les informations factuelles.\n"
            . "Contexte : note d'observation lors d'un inventaire d'actifs.\n"
            . "Note originale : \"{$text}\"\n"
            . "Réponds uniquement avec la note reformulée, sans explication.";

        return $this->executeWithFallback($org, $userId, 'rephrase', function (AiProviderInterface $provider) use ($prompt) {
            return $provider->generateText($prompt, ['max_tokens' => 500]);
        });
    }

    public function describePhoto(string $imagePath, Organization $org, string $userId, string $disk = null): AiAssistantResult
    {
        $prompt = "Tu es un assistant d'inventaire. Décris de manière factuelle et concise ce que tu vois "
            . "sur cette photo prise lors d'un inventaire d'actifs. Concentre-toi sur :\n"
            . "- L'état physique de l'objet (dommages, usure, propreté)\n"
            . "- Les détails identifiants visibles (marque, modèle, étiquettes, numéros de série)\n"
            . "- L'environnement immédiat si pertinent\n"
            . "Réponds en français, 2-4 phrases maximum.";

        $disk = $disk ?? config('media.disk', 's3');
        $imageContent = Storage::disk($disk)->get($imagePath);
        $base64 = base64_encode($imageContent);

        return $this->executeWithFallback($org, $userId, 'describe_photo', function (AiProviderInterface $provider) use ($base64, $prompt) {
            return $provider->analyzeImage(['photo' => $base64], $prompt, ['max_tokens' => 500]);
        });
    }

    public function transcribeAudio(string $audioPath, Organization $org, string $userId, string $disk = null, string $mimeType = 'audio/mpeg'): AiAssistantResult
    {
        $prompt = "Transcris fidèlement cet enregistrement audio. C'est une note vocale d'un agent "
            . "d'inventaire décrivant l'état d'un actif. Corrige les hésitations mineures mais "
            . "conserve le sens exact. Réponds uniquement avec la transcription.";

        $disk = $disk ?? config('media.disk', 's3');

        return $this->executeWithFallback($org, $userId, 'transcribe', function (AiProviderInterface $provider) use ($audioPath, $prompt, $disk, $mimeType) {
            return $provider->transcribeAudio($audioPath, $prompt, [
                'max_tokens' => 1000,
                'disk' => $disk,
                'mime_type' => $mimeType,
            ]);
        });
    }

    public function describeVideo(string $videoPath, Organization $org, string $userId, string $disk = null, string $mimeType = 'video/mp4'): AiAssistantResult
    {
        $prompt = "Tu es un assistant d'inventaire. Décris ce que tu observes dans cette courte vidéo "
            . "prise lors d'un inventaire d'actifs. Concentre-toi sur :\n"
            . "- L'objet filmé et son état\n"
            . "- Tout défaut ou dommage visible\n"
            . "- Les mouvements ou démonstrations (ex: l'agent montre un dysfonctionnement)\n"
            . "Réponds en français, 3-5 phrases maximum.";

        $disk = $disk ?? config('media.disk', 's3');

        return $this->executeWithFallback($org, $userId, 'describe_video', function (AiProviderInterface $provider) use ($videoPath, $prompt, $disk, $mimeType) {
            return $provider->analyzeVideo($videoPath, $prompt, [
                'max_tokens' => 1000,
                'disk' => $disk,
                'mime_type' => $mimeType,
            ]);
        });
    }

    public function generateText(string $prompt, Organization $org, string $userId): AiAssistantResult
    {
        return $this->executeWithFallback($org, $userId, 'generate_text', function (AiProviderInterface $provider) use ($prompt) {
            return $provider->generateText($prompt, ['max_tokens' => 1000]);
        });
    }

    public function canMakeRequest(Organization $org): bool
    {
        $dailyOk = $this->planLimitService->canCreate($org, PlanFeature::MaxAiRequestsDaily);
        $monthlyOk = $this->planLimitService->canCreate($org, PlanFeature::MaxAiRequestsMonthly);

        return $dailyOk && $monthlyOk;
    }

    protected function executeWithFallback(Organization $org, string $userId, string $feature, callable $callback): AiAssistantResult
    {
        $provider = $this->getProvider($org);
        $usedFallback = false;

        try {
            $response = $callback($provider);
        } catch (\Throwable $e) {
            Log::warning("AI assistant primary provider failed: {$e->getMessage()}", [
                'provider' => $provider->getProviderName(),
                'feature' => $feature,
            ]);

            $fallback = $this->getFallbackProvider($org);
            if (! $fallback) {
                throw $e;
            }

            $provider = $fallback;
            $usedFallback = true;
            $response = $callback($provider);
        }

        // Log usage
        $this->logUsage($org, $userId, $feature, $response->promptTokens + $response->completionTokens);

        return new AiAssistantResult(
            text: $response->text,
            provider: $provider->getProviderName(),
            usedFallback: $usedFallback,
            promptTokens: $response->promptTokens,
            completionTokens: $response->completionTokens,
            estimatedCostUsd: $response->estimatedCostUsd,
        );
    }

    protected function getProvider(Organization $org): AiProviderInterface
    {
        $plan = $this->planLimitService->getEffectivePlan($org);

        if ($plan->slug === 'premium') {
            return $this->openAiProvider;
        }

        return $this->geminiProvider;
    }

    protected function getFallbackProvider(Organization $org): ?AiProviderInterface
    {
        $plan = $this->planLimitService->getEffectivePlan($org);

        if ($plan->slug === 'premium') {
            return $this->geminiProvider;
        }

        if (in_array($plan->slug, ['basic', 'pro'])) {
            return $this->openAiProvider;
        }

        return null; // Freemium: no fallback
    }

    protected function logUsage(Organization $org, string $userId, string $feature, int $tokensUsed): void
    {
        AiUsageLog::create([
            'organization_id' => $org->id,
            'user_id' => $userId,
            'feature' => "assistant_{$feature}",
            'tokens_used' => $tokensUsed,
        ]);
    }
}
