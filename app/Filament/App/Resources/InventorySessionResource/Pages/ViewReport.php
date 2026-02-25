<?php

namespace App\Filament\App\Resources\InventorySessionResource\Pages;

use App\Filament\App\Resources\InventorySessionResource;
use App\Models\InventoryReport;
use App\Services\InventoryReportService;
use App\Services\StorageService;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewReport extends ViewRecord
{
    protected static string $resource = InventorySessionResource::class;

    protected static ?string $title = 'Rapport d\'inventaire';

    protected static ?string $breadcrumb = 'Rapport';

    public ?InventoryReport $report = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->report = InventoryReport::where('session_id', $this->record->id)
            ->where('type', 'session_report')
            ->latest()
            ->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->visible(fn () => $this->report !== null)
                ->action(function () {
                    $reportService = app(InventoryReportService::class);
                    $storageService = app(StorageService::class);

                    if (! $this->report->pdf_media_id) {
                        $media = $reportService->generatePdf($this->report);
                    } else {
                        $media = $this->report->pdfMedia;
                    }

                    $content = $storageService->getFileContents($media);
                    if ($content) {
                        return response()->streamDownload(
                            fn () => print($content),
                            $media->file_name,
                            ['Content-Type' => $media->mime_type],
                        );
                    }

                    // Fallback: regenerate if file not found
                    $media = $reportService->generatePdf($this->report);

                    return response()->streamDownload(
                        fn () => print($storageService->getFileContents($media)),
                        $media->file_name,
                        ['Content-Type' => $media->mime_type],
                    );
                }),

            Actions\Action::make('download_excel')
                ->label('Télécharger Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->visible(fn () => $this->report !== null)
                ->action(function () {
                    $reportService = app(InventoryReportService::class);
                    $storageService = app(StorageService::class);

                    if (! $this->report->excel_media_id) {
                        $media = $reportService->generateExcel($this->report);
                    } else {
                        $media = $this->report->excelMedia;
                    }

                    $content = $storageService->getFileContents($media);
                    if ($content) {
                        return response()->streamDownload(
                            fn () => print($content),
                            $media->file_name,
                            ['Content-Type' => $media->mime_type],
                        );
                    }

                    // Fallback: regenerate if file not found
                    $media = $reportService->generateExcel($this->report);

                    return response()->streamDownload(
                        fn () => print($storageService->getFileContents($media)),
                        $media->file_name,
                        ['Content-Type' => $media->mime_type],
                    );
                }),

            Actions\Action::make('ai_summary')
                ->label('Résumé IA')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->visible(fn () => $this->report !== null)
                ->requiresConfirmation()
                ->modalDescription('Générer ou régénérer le résumé IA pour ce rapport ?')
                ->action(function () {
                    $reportService = app(InventoryReportService::class);
                    $org = $this->record->organization;

                    $reportService->aiGenerateSummary($this->report, $org, auth()->id());

                    Notification::make()
                        ->title('Résumé IA généré')
                        ->success()
                        ->send();

                    $this->report->refresh();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $report = $this->report;

        if (! $report) {
            return $infolist->schema([
                Infolists\Components\TextEntry::make('no_report')
                    ->hiddenLabel()
                    ->default('Aucun rapport généré pour cette session.')
                    ->columnSpanFull(),
            ]);
        }

        $stats = $report->data ?? [];

        return $infolist
            ->record($this->record)
            ->schema([
                Infolists\Components\Section::make($report->title)
                    ->schema([
                        Infolists\Components\TextEntry::make('report_created')
                            ->label('Généré le')
                            ->default($report->created_at->format('d/m/Y à H:i')),

                        Infolists\Components\TextEntry::make('report_type')
                            ->label('Type')
                            ->default($report->type === 'session_report' ? 'Rapport de session' : 'Rapport de tâche')
                            ->badge(),
                    ])->columns(2),

                Infolists\Components\Section::make('Statistiques')
                    ->schema([
                        Infolists\Components\TextEntry::make('stat_expected')
                            ->label('Attendus')
                            ->default($stats['total_expected'] ?? 0),

                        Infolists\Components\TextEntry::make('stat_found')
                            ->label('Trouvés')
                            ->default($stats['total_found'] ?? 0)
                            ->color('success'),

                        Infolists\Components\TextEntry::make('stat_missing')
                            ->label('Manquants')
                            ->default($stats['total_missing'] ?? 0)
                            ->color('danger'),

                        Infolists\Components\TextEntry::make('stat_unexpected')
                            ->label('Inattendus')
                            ->default($stats['total_unexpected'] ?? 0)
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('stat_completion')
                            ->label('Complétion')
                            ->default(($stats['completion_rate'] ?? 0) . '%')
                            ->color('info'),
                    ])->columns(5),

                Infolists\Components\Section::make('Résumé')
                    ->schema([
                        Infolists\Components\TextEntry::make('report_summary')
                            ->hiddenLabel()
                            ->default($report->summary ?? 'Aucun résumé. Cliquez sur "Résumé IA" pour en générer un.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
