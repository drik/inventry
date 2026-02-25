<?php

namespace App\DTOs;

readonly class AiProviderResponse
{
    public function __construct(
        public string $text,
        public int $promptTokens,
        public int $completionTokens,
        public float $estimatedCostUsd,
    ) {}
}
