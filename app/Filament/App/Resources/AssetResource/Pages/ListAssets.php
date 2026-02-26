<?php

namespace App\Filament\App\Resources\AssetResource\Pages;

use App\Filament\App\Resources\AssetResource;
use App\Services\AiVisionService;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createWithAi')
                ->label('Créer avec IA')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->visible(fn () => config('ai-vision.enabled'))
                ->modalHeading('Créer un asset avec l\'IA')
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

                    session()->put('ai_asset_extraction', [
                        'recognition_log_id' => $result['recognition_log_id'],
                        'extraction' => $result['extraction']->toArray(),
                        'resolved_ids' => $result['resolved_ids'],
                        'image_paths' => $result['image_paths'],
                    ]);

                    $this->redirect(AssetResource::getUrl('create'));
                }),
            Actions\CreateAction::make(),
        ];
    }
}
