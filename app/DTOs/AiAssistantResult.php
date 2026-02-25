<?php

namespace App\DTOs;

readonly class AiAssistantResult
{
    public function __construct(
        public string $text,
        public string $provider,
        public bool $usedFallback,
        public int $promptTokens,
        public int $completionTokens,
        public float $estimatedCostUsd,
    ) {}

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'provider' => $this->provider,
            'used_fallback' => $this->usedFallback,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'estimated_cost_usd' => $this->estimatedCostUsd,
        ];
    }
}
