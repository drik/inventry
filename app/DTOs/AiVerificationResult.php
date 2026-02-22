<?php

namespace App\DTOs;

readonly class AiVerificationResult
{
    public function __construct(
        public bool $isMatch,
        public float $confidence,
        public string $reasoning,
        public array $discrepancies,
    ) {}

    public static function fromAiResponse(array $data): self
    {
        return new self(
            isMatch: (bool) ($data['is_match'] ?? false),
            confidence: (float) ($data['confidence'] ?? 0),
            reasoning: $data['reasoning'] ?? '',
            discrepancies: $data['discrepancies'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'is_match' => $this->isMatch,
            'confidence' => $this->confidence,
            'reasoning' => $this->reasoning,
            'discrepancies' => $this->discrepancies,
        ];
    }
}
