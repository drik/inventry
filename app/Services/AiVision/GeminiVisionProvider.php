<?php

namespace App\Services\AiVision;

use Gemini\Data\Blob;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\MimeType;
use Gemini\Enums\ResponseMimeType;
use Gemini\Laravel\Facades\Gemini;

class GeminiVisionProvider implements VisionProviderInterface
{
    public function analyze(array $images, string $prompt, array $options = []): array
    {
        $model = config('ai-vision.gemini.model', 'gemini-2.5-flash');
        $maxTokens = config('ai-vision.gemini.max_tokens', 1000);

        $parts = [];

        // Add the text prompt
        $parts[] = $prompt;

        // Add each image as a Blob
        foreach ($images as $label => $base64Data) {
            $parts[] = "Image: {$label}";
            $parts[] = new Blob(
                mimeType: MimeType::IMAGE_JPEG,
                data: $base64Data,
            );
        }

        $generationConfig = new GenerationConfig(
            maxOutputTokens: $maxTokens,
            responseMimeType: ResponseMimeType::APPLICATION_JSON,
        );

        $generativeModel = Gemini::generativeModel($model)
            ->withGenerationConfig($generationConfig);

        // Add system instruction if provided
        if (! empty($options['system'])) {
            $generativeModel = $generativeModel->withSystemInstruction(
                Content::parse($options['system'])
            );
        }

        $response = $generativeModel->generateContent($parts);

        $result = $response->json(true);
        $usage = $response->usageMetadata ?? null;

        return [
            'response' => $result,
            'prompt_tokens' => $usage?->promptTokenCount,
            'completion_tokens' => $usage?->candidatesTokenCount,
        ];
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    public function getModelName(): string
    {
        return config('ai-vision.gemini.model', 'gemini-2.5-flash');
    }
}
