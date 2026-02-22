<?php

namespace App\Services\AiVision;

use OpenAI\Laravel\Facades\OpenAI;

class OpenAiVisionProvider implements VisionProviderInterface
{
    public function analyze(array $images, string $prompt, array $options = []): array
    {
        $model = config('ai-vision.openai.model', 'gpt-4o');
        $maxTokens = config('ai-vision.openai.max_tokens', 1000);

        $messages = [];

        // System message
        if (! empty($options['system'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $options['system'],
            ];
        }

        // Build user content parts
        $contentParts = [];
        $contentParts[] = [
            'type' => 'text',
            'text' => $prompt,
        ];

        // Add each image
        foreach ($images as $label => $base64Data) {
            $contentParts[] = [
                'type' => 'text',
                'text' => "Image: {$label}",
            ];
            $contentParts[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:image/jpeg;base64,'.$base64Data,
                    'detail' => 'high',
                ],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $contentParts,
        ];

        $response = OpenAI::chat()->create([
            'model' => $model,
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => $maxTokens,
        ]);

        $content = $response->choices[0]->message->content;
        $result = json_decode($content, true);
        $usage = $response->usage;

        return [
            'response' => $result ?? [],
            'prompt_tokens' => $usage?->promptTokens,
            'completion_tokens' => $usage?->completionTokens,
        ];
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function getModelName(): string
    {
        return config('ai-vision.openai.model', 'gpt-4o');
    }
}
