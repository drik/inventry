<?php

namespace App\Services\AiProviders;

use App\DTOs\AiProviderResponse;

interface AiProviderInterface
{
    public function generateText(string $prompt, array $options = []): AiProviderResponse;

    public function analyzeImage(array $images, string $prompt, array $options = []): AiProviderResponse;

    public function transcribeAudio(string $audioPath, string $prompt, array $options = []): AiProviderResponse;

    public function analyzeVideo(string $videoPath, string $prompt, array $options = []): AiProviderResponse;

    public function getProviderName(): string;
}
