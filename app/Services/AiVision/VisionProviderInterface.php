<?php

namespace App\Services\AiVision;

interface VisionProviderInterface
{
    /**
     * Analyze images with a prompt and return structured results.
     *
     * @param  array<string, string>  $images  Associative array of label => base64 image data
     * @param  string  $prompt  The prompt to send
     * @param  array  $options  Additional options (e.g. system instruction)
     * @return array{response: array, prompt_tokens: int|null, completion_tokens: int|null}
     */
    public function analyze(array $images, string $prompt, array $options = []): array;

    public function getProviderName(): string;

    public function getModelName(): string;
}
