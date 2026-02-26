<?php

namespace App\DTOs;

use Carbon\Carbon;

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
        public ?float $purchaseCost = null,
        public ?string $purchaseDate = null,
        public ?string $warrantyExpiry = null,
        public ?string $suggestedLocation = null,
        public ?string $suggestedSupplier = null,
    ) {}

    public static function fromAiResponse(array $data): self
    {
        // Calculate warranty_expiry from duration if not directly provided
        $warrantyExpiry = $data['warranty_expiry'] ?? null;
        if (! $warrantyExpiry && ($data['warranty_duration_months'] ?? null) && ($data['purchase_date'] ?? null)) {
            try {
                $warrantyExpiry = Carbon::parse($data['purchase_date'])
                    ->addMonths((int) $data['warranty_duration_months'])
                    ->toDateString();
            } catch (\Exception $e) {
                // Ignore invalid date
            }
        }

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
            purchaseCost: isset($data['purchase_cost']) ? (float) $data['purchase_cost'] : null,
            purchaseDate: $data['purchase_date'] ?? null,
            warrantyExpiry: $warrantyExpiry,
            suggestedLocation: $data['suggested_location'] ?? null,
            suggestedSupplier: $data['suggested_supplier'] ?? null,
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
            'purchase_cost' => $this->purchaseCost,
            'purchase_date' => $this->purchaseDate,
            'warranty_expiry' => $this->warrantyExpiry,
            'suggested_location' => $this->suggestedLocation,
            'suggested_supplier' => $this->suggestedSupplier,
        ];
    }
}
