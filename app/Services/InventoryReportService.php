<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryReport;
use App\Models\InventorySession;
use App\Models\InventoryTask;
use App\Models\Media;
use App\Models\Organization;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InventoryReportService
{
    public function __construct(
        protected StorageService $storageService,
        protected AiAssistantService $aiService,
    ) {}

    public function generateTaskReport(InventoryTask $task, string $userId): InventoryReport
    {
        $session = $task->session;
        $org = Organization::find($task->organization_id);

        $items = InventoryItem::withoutGlobalScopes()
            ->where('task_id', $task->id)
            ->with(['asset', 'condition'])
            ->get();

        $stats = $this->computeItemStats($items);

        $report = InventoryReport::create([
            'organization_id' => $task->organization_id,
            'session_id' => $session->id,
            'task_id' => $task->id,
            'type' => 'task_report',
            'title' => "Rapport — {$session->name} / {$task->location?->name}",
            'data' => $stats,
            'generated_by' => $userId,
        ]);

        return $report;
    }

    public function generateSessionReport(InventorySession $session, string $userId): InventoryReport
    {
        $items = InventoryItem::withoutGlobalScopes()
            ->where('session_id', $session->id)
            ->with(['asset', 'condition', 'task.location'])
            ->get();

        $stats = $this->computeItemStats($items);

        // Per-task breakdown
        $taskBreakdown = $items->groupBy('task_id')->map(function ($taskItems, $taskId) {
            $task = $taskItems->first()->task;

            return [
                'task_id' => $taskId,
                'location_name' => $task?->location?->name ?? 'Non assigné',
                'assignee' => $task?->assignee?->name ?? 'Non assigné',
                'stats' => $this->computeItemStats($taskItems),
            ];
        })->values()->toArray();

        $stats['task_breakdown'] = $taskBreakdown;

        $report = InventoryReport::create([
            'organization_id' => $session->organization_id,
            'session_id' => $session->id,
            'task_id' => null,
            'type' => 'session_report',
            'title' => "Rapport consolidé — {$session->name}",
            'data' => $stats,
            'generated_by' => $userId,
        ]);

        return $report;
    }

    public function generatePdf(InventoryReport $report): Media
    {
        $view = $report->type === 'task_report'
            ? 'reports.task-report'
            : 'reports.session-report';

        $data = $this->prepareReportData($report);

        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4')
            ->setOption(['dpi' => 150, 'isRemoteEnabled' => true]);

        $content = $pdf->output();
        $org = $report->organization;
        $disk = config('media.disk', 's3');
        $path = implode('/', [
            $org->id,
            'reports',
            now()->format('Y/m/d'),
            Str::uuid() . '.pdf',
        ]);

        Storage::disk($disk)->put($path, $content);

        $media = Media::create([
            'organization_id' => $org->id,
            'mediable_type' => InventoryReport::class,
            'mediable_id' => $report->id,
            'collection' => 'documents',
            'disk' => $disk,
            'file_path' => $path,
            'file_name' => Str::slug($report->title) . '.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'metadata' => ['type' => 'report_pdf'],
            'uploaded_by' => $report->generated_by,
        ]);

        $report->update(['pdf_media_id' => $media->id]);

        return $media;
    }

    public function generateExcel(InventoryReport $report): Media
    {
        $data = $this->prepareReportData($report);
        $export = $report->type === 'task_report'
            ? new \App\Exports\TaskReportExport($data)
            : new \App\Exports\SessionReportExport($data);

        $org = $report->organization;
        $disk = config('media.disk', 's3');
        $path = implode('/', [
            $org->id,
            'reports',
            now()->format('Y/m/d'),
            Str::uuid() . '.xlsx',
        ]);

        $tempFileName = 'report_' . Str::uuid() . '.xlsx';
        \Maatwebsite\Excel\Facades\Excel::store($export, $tempFileName, 'local');

        $localPath = Storage::disk('local')->path($tempFileName);
        $content = file_get_contents($localPath);
        Storage::disk($disk)->put($path, $content);
        @unlink($localPath);

        $media = Media::create([
            'organization_id' => $org->id,
            'mediable_type' => InventoryReport::class,
            'mediable_id' => $report->id,
            'collection' => 'documents',
            'disk' => $disk,
            'file_path' => $path,
            'file_name' => Str::slug($report->title) . '.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size_bytes' => strlen($content),
            'metadata' => ['type' => 'report_excel'],
            'uploaded_by' => $report->generated_by,
        ]);

        $report->update(['excel_media_id' => $media->id]);

        return $media;
    }

    public function aiGenerateSummary(InventoryReport $report, Organization $org, string $userId): string
    {
        $data = $report->data;

        $prompt = "Tu es un expert en gestion d'inventaire. Génère un résumé professionnel de ce rapport d'inventaire.\n\n"
            . "Statistiques :\n"
            . "- Total attendus : {$data['total_expected']}\n"
            . "- Trouvés : {$data['total_found']}\n"
            . "- Manquants : {$data['total_missing']}\n"
            . "- Inattendus : {$data['total_unexpected']}\n"
            . "- Taux de complétion : {$data['completion_rate']}%\n\n"
            . "Rédige un paragraphe de 3-5 phrases résumant les résultats et identifiant les points d'attention. "
            . "Réponds en français.";

        $result = $this->aiService->generateText($prompt, $org, $userId);

        $report->update([
            'ai_summary' => $result->text,
            'summary' => $result->text,
        ]);

        return $result->text;
    }

    protected function computeItemStats($items): array
    {
        $total = $items->count();
        $found = $items->where('status.value', 'found')->count();
        $missing = $items->where('status.value', 'missing')->count();
        $unexpected = $items->where('status.value', 'unexpected')->count();
        $expected = $items->where('status.value', 'expected')->count();

        $conditionBreakdown = $items->groupBy(fn ($item) => $item->condition?->name ?? 'Non spécifié')
            ->map->count()
            ->toArray();

        return [
            'total_expected' => $total - $unexpected,
            'total_found' => $found,
            'total_missing' => $missing,
            'total_unexpected' => $unexpected,
            'total_pending' => $expected,
            'completion_rate' => ($total - $unexpected) > 0
                ? round(($found / ($total - $unexpected)) * 100, 1)
                : 0,
            'condition_breakdown' => $conditionBreakdown,
        ];
    }

    protected function prepareReportData(InventoryReport $report): array
    {
        $session = $report->session;
        $org = $report->organization;

        $items = InventoryItem::withoutGlobalScopes()
            ->where('session_id', $session->id)
            ->when($report->task_id, fn ($q) => $q->where('task_id', $report->task_id))
            ->with(['asset.category', 'condition', 'notes', 'scanner'])
            ->get();

        $task = $report->task;

        return [
            'report' => $report,
            'session' => $session,
            'organization' => $org,
            'task' => $task,
            'items' => $items,
            'stats' => $report->data,
        ];
    }
}
