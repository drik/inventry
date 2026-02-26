<?php

namespace App\Filament\App\Resources\AssetResource\Pages;

use App\Enums\PlanFeature;
use App\Filament\App\Resources\AssetResource;
use App\Filament\App\Resources\AssetResource\Concerns\ConfirmsSuggestedEntities;
use App\Filament\App\Resources\AssetResource\Concerns\ValidatesUniqueTagValues;
use App\Filament\Concerns\ChecksPlanLimits;
use App\Models\AiRecognitionLog;
use App\Models\AssetCategory;
use App\Models\AssetImage;
use App\Models\AssetTag;
use App\Services\AiVisionService;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateAsset extends CreateRecord
{
    use ChecksPlanLimits, ConfirmsSuggestedEntities, ValidatesUniqueTagValues;

    protected static string $resource = AssetResource::class;

    public ?string $aiRecognitionLogId = null;

    public ?array $aiImagePaths = null;

    protected static function getPlanFeature(): ?PlanFeature
    {
        return PlanFeature::MaxAssets;
    }

    public function mount(): void
    {
        parent::mount();

        // Check for AI extraction data from session (coming from ListAssets)
        $aiData = session()->pull('ai_asset_extraction');
        if ($aiData) {
            $this->fillFormFromAiData($aiData);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fillWithAi')
                ->label('Remplir avec l\'IA')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->visible(fn () => config('ai-vision.enabled'))
                ->modalHeading('Remplir avec l\'IA')
                ->modalDescription('Sélectionnez une ou plusieurs photos du produit (différents angles, étiquettes...). L\'IA combinera les informations de toutes les photos.')
                ->modalSubmitActionLabel('Analyser avec l\'IA')
                ->modalWidth('md')
                ->form([
                    Forms\Components\FileUpload::make('photos')
                        ->label('Photos du produit')
                        ->image()
                        ->multiple()
                        ->maxFiles(5)
                        ->required()
                        ->acceptedFileTypes(['image/jpeg', 'image/png'])
                        ->maxSize(2048)
                        ->disk('public')
                        ->directory('ai-captures')
                        ->imagePreviewHeight('200'),
                ])
                ->action(function (array $data) {
                    $org = Filament::getTenant();
                    $aiService = app(AiVisionService::class);

                    if (! $aiService->canMakeRequest($org)) {
                        Notification::make()
                            ->title('Quota IA dépassé')
                            ->body('Vous avez atteint la limite de requêtes IA pour votre plan.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $storagePaths = (array) $data['photos'];
                    $absolutePaths = array_map(
                        fn ($p) => Storage::disk('public')->path($p),
                        $storagePaths,
                    );

                    $result = $aiService->extractAssetInfo(
                        imagePaths: $absolutePaths,
                        organization: $org,
                        storagePaths: $storagePaths,
                    );

                    $this->fillFormFromAiData([
                        'recognition_log_id' => $result['recognition_log_id'],
                        'extraction' => $result['extraction']->toArray(),
                        'resolved_ids' => $result['resolved_ids'],
                        'image_paths' => $result['image_paths'],
                    ]);

                    Notification::make()
                        ->title('Analyse terminée')
                        ->body('Les informations extraites ont été pré-remplies dans le formulaire.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function fillFormFromAiData(array $aiData): void
    {
        $extraction = $aiData['extraction'];
        $resolvedIds = $aiData['resolved_ids'];

        $this->aiRecognitionLogId = $aiData['recognition_log_id'];
        $this->aiImagePaths = $aiData['image_paths'] ?? (isset($aiData['image_path']) ? [$aiData['image_path']] : null);

        // Use $this->data directly (getRawState/getState validates required fields and fails on empty form)
        $formData = $this->data ?? [];

        if (! empty($extraction['suggested_name'])) {
            $formData['name'] = $extraction['suggested_name'];
        }

        if (! empty($resolvedIds['category_id'])) {
            $formData['category_id'] = $resolvedIds['category_id'];
        }

        if (! empty($resolvedIds['manufacturer_id'])) {
            $formData['manufacturer_id'] = $resolvedIds['manufacturer_id'];
        }

        if (! empty($resolvedIds['model_id'])) {
            $formData['model_id'] = $resolvedIds['model_id'];
        }

        // Location (resolved or suggested)
        if (! empty($resolvedIds['location_id'])) {
            $formData['location_id'] = $resolvedIds['location_id'];
        }

        // Supplier (resolved or suggested)
        if (! empty($resolvedIds['supplier_id'])) {
            $formData['supplier_id'] = $resolvedIds['supplier_id'];
        }

        // Financial fields
        if (! empty($extraction['purchase_cost'])) {
            $formData['purchase_cost'] = $extraction['purchase_cost'];
        }
        if (! empty($extraction['purchase_date'])) {
            $formData['purchase_date'] = $extraction['purchase_date'];
        }
        if (! empty($extraction['warranty_expiry'])) {
            $formData['warranty_expiry'] = $extraction['warranty_expiry'];
        }

        // Build notes from AI extraction
        $notes = '';
        if (! empty($extraction['description'])) {
            $notes = $extraction['description'];
        }
        if (! empty($resolvedIds['unmatched_suggestions'])) {
            $suggestions = [];
            foreach ($resolvedIds['unmatched_suggestions'] as $field => $value) {
                $suggestions[] = ucfirst($field) . ': ' . $value;
            }
            if ($suggestions) {
                $notes .= ($notes ? '<br>' : '') . '<em>Suggestions IA non résolues : ' . implode(', ', $suggestions) . '</em>';
            }
        }
        if ($notes) {
            $formData['notes'] = $notes;
        }

        // Fill tag values if category was resolved (serial_number, sku)
        if (! empty($resolvedIds['category_id'])) {
            $orgId = Filament::getTenant()->id;
            $tags = AssetCategory::getAllTagsForCategory($resolvedIds['category_id'], $orgId);

            $tagValues = [];
            foreach ($tags as $tag) {
                $value = '';

                if ($tag->is_system && $tag->name === 'Serial Number' && ! empty($extraction['serial_number'])) {
                    $value = $extraction['serial_number'];
                } elseif ($tag->is_system && $tag->name === 'SKU' && ! empty($extraction['sku'])) {
                    $value = $extraction['sku'];
                }

                $tagValues[] = [
                    'asset_tag_id' => $tag->id,
                    'value' => $value,
                    'encoding_mode' => $tag->encoding_mode?->value,
                ];
            }

            $formData['tagValues'] = $tagValues;
        }

        $this->form->fill($formData);
    }

    protected function beforeCreate(): void
    {
        $this->validateUniqueTagValues();
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        // Confirm suggested entities (implicit approval)
        $this->confirmSuggestedEntities($record);

        // If this asset was created via AI photos, save them as images
        if ($this->aiImagePaths) {
            foreach ($this->aiImagePaths as $index => $imagePath) {
                if (Storage::disk('public')->exists($imagePath)) {
                    AssetImage::create([
                        'organization_id' => $record->organization_id,
                        'asset_id' => $record->id,
                        'file_path' => $imagePath,
                        'caption' => 'Photo IA',
                        'is_primary' => $index === 0,
                        'sort_order' => $index,
                    ]);
                }
            }
        }

        // Update the recognition log with the created asset
        if ($this->aiRecognitionLogId) {
            AiRecognitionLog::withoutGlobalScopes()
                ->where('id', $this->aiRecognitionLogId)
                ->update([
                    'selected_asset_id' => $record->id,
                    'selected_action' => 'created',
                ]);
        }
    }
}
