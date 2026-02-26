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
        $maxTokens = $options['max_tokens'] ?? config('ai-vision.gemini.max_tokens', 4096);

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

        if (! is_array($result)) {
            // Try to repair truncated JSON
            $rawText = $response->text() ?? '';
            $result = $this->tryRepairJson($rawText);

            if (! is_array($result)) {
                throw new \RuntimeException("Gemini returned invalid JSON response: " . mb_substr($rawText, 0, 500));
            }
        }

        return [
            'response' => $result,
            'prompt_tokens' => $usage?->promptTokenCount,
            'completion_tokens' => $usage?->candidatesTokenCount,
        ];
    }

    /**
     * Attempt to repair truncated JSON by closing open structures.
     */
    protected function tryRepairJson(string $json): ?array
    {
        $json = trim($json);
        if (empty($json)) {
            return null;
        }

        // Strip markdown code fences if present
        if (str_starts_with($json, '```')) {
            $json = preg_replace('/^```(?:json)?\s*/i', '', $json);
            $json = preg_replace('/\s*```\s*$/', '', $json);
        }

        // First try as-is
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Truncated string value: remove incomplete string at the end and close structures
        // Find the last complete key-value pair by removing trailing incomplete content
        $repaired = $json;

        // Remove trailing incomplete string (e.g., `"71 Quai Saint` without closing quote)
        $repaired = preg_replace('/, *"[^"]*$/', '', $repaired);       // trailing: , "incomplete...
        $repaired = preg_replace('/"[^"]*$/', '"', $repaired);          // trailing: "incomplete... → close quote
        $repaired = preg_replace('/: *"[^"]*$/', ': null', $repaired);  // trailing: : "incomplete... → null

        // Close any open arrays and objects
        $openBraces = substr_count($repaired, '{') - substr_count($repaired, '}');
        $openBrackets = substr_count($repaired, '[') - substr_count($repaired, ']');

        // Remove trailing comma before closing
        $repaired = preg_replace('/,\s*$/', '', $repaired);

        // Close open brackets first (arrays inside objects), then braces
        $repaired .= str_repeat(']', max(0, $openBrackets));
        $repaired .= str_repeat('}', max(0, $openBraces));

        $decoded = json_decode($repaired, true);
        if (is_array($decoded)) {
            \Log::warning('Gemini response JSON was truncated and auto-repaired', [
                'original_length' => strlen($json),
                'repaired_length' => strlen($repaired),
            ]);

            return $decoded;
        }

        return null;
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
