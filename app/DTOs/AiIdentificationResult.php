<?php

namespace App\DTOs;

readonly class AiIdentificationResult
{
    public function __construct(
        public ?string $suggestedCategory,
        public ?string $suggestedBrand,
        public ?string $suggestedModel,
        public array $detectedText,
        public float $confidence,
        public ?string $description,
    ) {}

    public static function fromAiResponse(array $data): self
    {
        return new self(
            suggestedCategory: $data['suggested_category'] ?? null,
            suggestedBrand: $data['suggested_brand'] ?? null,
            suggestedModel: $data['suggested_model'] ?? null,
            detectedText: $data['detected_text'] ?? [],
            confidence: (float) ($data['confidence'] ?? 0),
            description: $data['description'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'suggested_category' => $this->suggestedCategory,
            'suggested_brand' => $this->suggestedBrand,
            'suggested_model' => $this->suggestedModel,
            'detected_text' => $this->detectedText,
            'confidence' => $this->confidence,
            'description' => $this->description,
        ];
    }
}
