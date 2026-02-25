<?php

namespace App\Services\AiProviders;

use App\DTOs\AiProviderResponse;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAiProvider implements AiProviderInterface
{
    protected string $chatModel;

    protected float $inputCostPer1M;

    protected float $outputCostPer1M;

    public function __construct()
    {
        $this->chatModel = config('ai-vision.openai.model', 'gpt-4o');
        $this->inputCostPer1M = 2.50;
        $this->outputCostPer1M = 10.00;
    }

    public function generateText(string $prompt, array $options = []): AiProviderResponse
    {
        $maxTokens = $options['max_tokens'] ?? 500;

        $messages = [];
        if (! empty($options['system'])) {
            $messages[] = ['role' => 'system', 'content' => $options['system']];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = OpenAI::chat()->create([
            'model' => $this->chatModel,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ]);

        $usage = $response->usage;

        return new AiProviderResponse(
            text: $response->choices[0]->message->content ?? '',
            promptTokens: $usage?->promptTokens ?? 0,
            completionTokens: $usage?->completionTokens ?? 0,
            estimatedCostUsd: $this->estimateCost($usage?->promptTokens ?? 0, $usage?->completionTokens ?? 0),
        );
    }

    public function analyzeImage(array $images, string $prompt, array $options = []): AiProviderResponse
    {
        $maxTokens = $options['max_tokens'] ?? 500;

        $messages = [];
        if (! empty($options['system'])) {
            $messages[] = ['role' => 'system', 'content' => $options['system']];
        }

        $contentParts = [];
        $contentParts[] = ['type' => 'text', 'text' => $prompt];

        foreach ($images as $label => $base64Data) {
            $contentParts[] = ['type' => 'text', 'text' => "Image: {$label}"];
            $contentParts[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:image/jpeg;base64,' . $base64Data,
                    'detail' => 'high',
                ],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $contentParts];

        $response = OpenAI::chat()->create([
            'model' => $this->chatModel,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ]);

        $usage = $response->usage;

        return new AiProviderResponse(
            text: $response->choices[0]->message->content ?? '',
            promptTokens: $usage?->promptTokens ?? 0,
            completionTokens: $usage?->completionTokens ?? 0,
            estimatedCostUsd: $this->estimateCost($usage?->promptTokens ?? 0, $usage?->completionTokens ?? 0),
        );
    }

    public function transcribeAudio(string $audioPath, string $prompt, array $options = []): AiProviderResponse
    {
        $disk = $options['disk'] ?? config('media.disk', 's3');
        $audioContent = Storage::disk($disk)->get($audioPath);

        $tempFile = tempnam(sys_get_temp_dir(), 'audio_');
        $extension = pathinfo($audioPath, PATHINFO_EXTENSION) ?: 'mp3';
        $tempPath = $tempFile . '.' . $extension;
        file_put_contents($tempPath, $audioContent);

        try {
            $response = OpenAI::audio()->transcribe([
                'model' => 'whisper-1',
                'file' => fopen($tempPath, 'r'),
                'language' => 'fr',
                'prompt' => $prompt,
            ]);

            return new AiProviderResponse(
                text: $response->text ?? '',
                promptTokens: 0,
                completionTokens: 0,
                estimatedCostUsd: 0.006, // Whisper: $0.006/min, estimate 1 min
            );
        } finally {
            @unlink($tempPath);
            @unlink($tempFile);
        }
    }

    public function analyzeVideo(string $videoPath, string $prompt, array $options = []): AiProviderResponse
    {
        // OpenAI doesn't natively support video — extract frames and analyze as images
        // For now, return a message suggesting Gemini for video
        return new AiProviderResponse(
            text: 'L\'analyse vidéo n\'est pas disponible avec ce fournisseur. Veuillez utiliser Gemini.',
            promptTokens: 0,
            completionTokens: 0,
            estimatedCostUsd: 0,
        );
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    protected function estimateCost(int $promptTokens, int $completionTokens): float
    {
        return ($promptTokens * $this->inputCostPer1M / 1_000_000)
            + ($completionTokens * $this->outputCostPer1M / 1_000_000);
    }
}
