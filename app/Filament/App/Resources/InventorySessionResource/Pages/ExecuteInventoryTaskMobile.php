<?php

namespace App\Filament\App\Resources\InventorySessionResource\Pages;

use App\Filament\App\Resources\InventorySessionResource;

class ExecuteInventoryTaskMobile extends ExecuteInventoryTask
{
    protected static string $resource = InventorySessionResource::class;

    protected static string $view = 'filament.app.resources.inventory-session-resource.pages.execute-inventory-task-mobile';

    protected static ?string $title = 'Mobile Scanner';

    public function scanBarcode(): void
    {
        parent::scanBarcode();

        $this->dispatch('mobile-scan-feedback', [
            'type' => $this->scanFeedbackType,
            'message' => $this->scanFeedback,
        ]);
    }

    public function addUnexpected(?string $assetId = null): void
    {
        parent::addUnexpected($assetId);

        $this->dispatch('mobile-scan-feedback', [
            'type' => $this->scanFeedbackType,
            'message' => $this->scanFeedback,
        ]);
    }
}
