<?php

namespace App\DTOs;

readonly class AiAssetExtractionResult
{
    public function __construct(
        public ?string $suggestedName,
        public ?string $suggestedCategory,
        public ?string $suggestedBrand,
        public ?string $suggestedModel,
        public ?string $suggestedDescription,
        public ?string $serialNumber,
        public ?string $sku,
        public array $detectedText,
        public float $confidence,
    ) {}

    public static function fromAiResponse(array $data): self
    {
        return new self(
            suggestedName: $data['suggested_name'] ?? null,
            suggestedCategory: $data['suggested_category'] ?? null,
            suggestedBrand: $data['suggested_brand'] ?? null,
            suggestedModel: $data['suggested_model'] ?? null,
            suggestedDescription: $data['description'] ?? null,
            serialNumber: $data['serial_number'] ?? null,
            sku: $data['sku'] ?? null,
            detectedText: $data['detected_text'] ?? [],
            confidence: (float) ($data['confidence'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'suggested_name' => $this->suggestedName,
            'suggested_category' => $this->suggestedCategory,
            'suggested_brand' => $this->suggestedBrand,
            'suggested_model' => $this->suggestedModel,
            'description' => $this->suggestedDescription,
            'serial_number' => $this->serialNumber,
            'sku' => $this->sku,
            'detected_text' => $this->detectedText,
            'confidence' => $this->confidence,
        ];
    }
}
