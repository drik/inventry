<?php

namespace App\DTOs;

readonly class AiMatchResult
{
    public function __construct(
        public string $referenceKey,
        public string $assetId,
        public float $confidence,
        public string $reasoning,
    ) {}

    public static function fromAiResponse(array $data, array $candidateMap): self
    {
        $refKey = $data['reference_key'] ?? '';
        $assetId = $candidateMap[$refKey] ?? '';

        return new self(
            referenceKey: $refKey,
            assetId: $assetId,
            confidence: (float) ($data['confidence'] ?? 0),
            reasoning: $data['reasoning'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'reference_key' => $this->referenceKey,
            'asset_id' => $this->assetId,
            'confidence' => $this->confidence,
            'reasoning' => $this->reasoning,
        ];
    }
}
