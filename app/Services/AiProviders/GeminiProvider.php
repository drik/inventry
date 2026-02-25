<?php

namespace App\Services\AiProviders;

use App\DTOs\AiProviderResponse;
use Gemini\Data\Blob;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Storage;

class GeminiProvider implements AiProviderInterface
{
    protected string $model;

    protected float $inputCostPer1M;

    protected float $outputCostPer1M;

    public function __construct()
    {
        $this->model = config('ai-vision.gemini.model', 'gemini-2.5-flash');
        $this->inputCostPer1M = 0.075;
        $this->outputCostPer1M = 0.30;
    }

    public function generateText(string $prompt, array $options = []): AiProviderResponse
    {
        $maxTokens = $options['max_tokens'] ?? 500;

        $generationConfig = new GenerationConfig(
            maxOutputTokens: $maxTokens,
        );

        $generativeModel = Gemini::generativeModel($this->model)
            ->withGenerationConfig($generationConfig);

        if (! empty($options['system'])) {
            $generativeModel = $generativeModel->withSystemInstruction(
                Content::parse($options['system'])
            );
        }

        $response = $generativeModel->generateContent([$prompt]);
        $usage = $response->usageMetadata ?? null;

        $promptTokens = $usage?->promptTokenCount ?? 0;
        $completionTokens = $usage?->candidatesTokenCount ?? 0;

        return new AiProviderResponse(
            text: $this->safeExtractText($response),
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            estimatedCostUsd: $this->estimateCost($promptTokens, $completionTokens),
        );
    }

    public function analyzeImage(array $images, string $prompt, array $options = []): AiProviderResponse
    {
        $maxTokens = $options['max_tokens'] ?? 500;

        $parts = [];
        $parts[] = $prompt;

        foreach ($images as $label => $base64Data) {
            $parts[] = "Image: {$label}";
            $parts[] = new Blob(
                mimeType: MimeType::IMAGE_JPEG,
                data: $base64Data,
            );
        }

        $generationConfig = new GenerationConfig(
            maxOutputTokens: $maxTokens,
        );

        $generativeModel = Gemini::generativeModel($this->model)
            ->withGenerationConfig($generationConfig);

        if (! empty($options['system'])) {
            $generativeModel = $generativeModel->withSystemInstruction(
                Content::parse($options['system'])
            );
        }

        $response = $generativeModel->generateContent($parts);
        $usage = $response->usageMetadata ?? null;

        $promptTokens = $usage?->promptTokenCount ?? 0;
        $completionTokens = $usage?->candidatesTokenCount ?? 0;

        return new AiProviderResponse(
            text: $this->safeExtractText($response),
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            estimatedCostUsd: $this->estimateCost($promptTokens, $completionTokens),
        );
    }

    public function transcribeAudio(string $audioPath, string $prompt, array $options = []): AiProviderResponse
    {
        $maxTokens = $options['max_tokens'] ?? 1000;

        // Gemini 2.5 Flash supports native audio input
        $disk = $options['disk'] ?? config('media.disk', 's3');
        $audioContent = Storage::disk($disk)->get($audioPath);
        $mimeType = $options['mime_type'] ?? 'audio/mpeg';

        $geminiMime = match ($mimeType) {
            'audio/mpeg', 'audio/mp3' => MimeType::AUDIO_MP3,
            'audio/wav' => MimeType::AUDIO_WAV,
            'audio/ogg', 'audio/webm' => MimeType::AUDIO_OGG,
            'audio/aac', 'audio/m4a' => MimeType::AUDIO_AAC,
            'audio/flac' => MimeType::AUDIO_FLAC,
            default => MimeType::AUDIO_MP3,
        };

        $parts = [];
        $parts[] = $prompt;
        $parts[] = new Blob(
            mimeType: $geminiMime,
            data: base64_encode($audioContent),
        );

        $generationConfig = new GenerationConfig(
            maxOutputTokens: $maxTokens,
        );

        $generativeModel = Gemini::generativeModel($this->model)
            ->withGenerationConfig($generationConfig);

        $response = $generativeModel->generateContent($parts);
        $usage = $response->usageMetadata ?? null;

        $promptTokens = $usage?->promptTokenCount ?? 0;
        $completionTokens = $usage?->candidatesTokenCount ?? 0;

        return new AiProviderResponse(
            text: $this->safeExtractText($response),
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            estimatedCostUsd: $this->estimateCost($promptTokens, $completionTokens),
        );
    }

    public function analyzeVideo(string $videoPath, string $prompt, array $options = []): AiProviderResponse
    {
        $maxTokens = $options['max_tokens'] ?? 1000;

        $disk = $options['disk'] ?? config('media.disk', 's3');
        $videoContent = Storage::disk($disk)->get($videoPath);
        $mimeType = $options['mime_type'] ?? 'video/mp4';

        $geminiMime = match ($mimeType) {
            'video/mp4' => MimeType::VIDEO_MP4,
            'video/webm' => MimeType::VIDEO_WEBM,
            'video/quicktime', 'video/mov' => MimeType::VIDEO_MOV,
            'video/mpeg' => MimeType::VIDEO_MPEG,
            default => MimeType::VIDEO_MP4,
        };

        $parts = [];
        $parts[] = $prompt;
        $parts[] = new Blob(
            mimeType: $geminiMime,
            data: base64_encode($videoContent),
        );

        $generationConfig = new GenerationConfig(
            maxOutputTokens: $maxTokens,
        );

        $generativeModel = Gemini::generativeModel($this->model)
            ->withGenerationConfig($generationConfig);

        $response = $generativeModel->generateContent($parts);
        $usage = $response->usageMetadata ?? null;

        $promptTokens = $usage?->promptTokenCount ?? 0;
        $completionTokens = $usage?->candidatesTokenCount ?? 0;

        return new AiProviderResponse(
            text: $this->safeExtractText($response),
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            estimatedCostUsd: $this->estimateCost($promptTokens, $completionTokens),
        );
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    protected function safeExtractText($response): string
    {
        try {
            return $response->text() ?? '';
        } catch (\ValueError $e) {
            // Response was blocked by safety filters or returned no valid parts
            throw new \RuntimeException(
                'Le contenu a été bloqué par les filtres de sécurité de l\'IA. Veuillez réessayer avec un autre fichier.',
                422,
                $e
            );
        }
    }

    protected function estimateCost(int $promptTokens, int $completionTokens): float
    {
        return ($promptTokens * $this->inputCostPer1M / 1_000_000)
            + ($completionTokens * $this->outputCostPer1M / 1_000_000);
    }
}
