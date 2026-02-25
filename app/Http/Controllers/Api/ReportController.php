<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryReport;
use App\Models\InventorySession;
use App\Models\InventoryTask;
use App\Services\InventoryReportService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        protected InventoryReportService $reportService,
        protected StorageService $storageService,
    ) {}

    /**
     * Generate a task report.
     */
    public function generateTaskReport(Request $request, string $taskId): JsonResponse
    {
        $task = InventoryTask::withoutGlobalScopes()
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($taskId);

        $report = $this->reportService->generateTaskReport($task, $request->user()->id);

        return response()->json([
            'report' => $this->formatReport($report),
        ], 201);
    }

    /**
     * Show task report.
     */
    public function showTaskReport(Request $request, string $taskId): JsonResponse
    {
        $report = InventoryReport::where('organization_id', $request->user()->organization_id)
            ->where('task_id', $taskId)
            ->where('type', 'task_report')
            ->latest()
            ->firstOrFail();

        return response()->json([
            'report' => $this->formatReport($report),
        ]);
    }

    /**
     * Show session report.
     */
    public function showSessionReport(Request $request, string $sessionId): JsonResponse
    {
        $report = InventoryReport::where('organization_id', $request->user()->organization_id)
            ->where('session_id', $sessionId)
            ->where('type', 'session_report')
            ->latest()
            ->firstOrFail();

        return response()->json([
            'report' => $this->formatReport($report),
        ]);
    }

    /**
     * Download report as PDF.
     */
    public function pdf(Request $request, string $reportId): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $report = InventoryReport::where('organization_id', $request->user()->organization_id)
            ->findOrFail($reportId);

        if (! $report->pdf_media_id) {
            $media = $this->reportService->generatePdf($report);
        } else {
            $media = $report->pdfMedia;
        }

        // Try signed URL first (for S3), fall back to direct download
        try {
            $url = $this->storageService->getSignedUrl($media);

            return response()->json([
                'url' => $url,
                'file_name' => $media->file_name,
            ]);
        } catch (\RuntimeException $e) {
            // S3 not configured — try direct file content
            $content = $this->storageService->getFileContents($media);
            if (! $content) {
                // Regenerate if file not found
                $media = $this->reportService->generatePdf($report);
                $content = $this->storageService->getFileContents($media);
            }

            return response()->streamDownload(
                fn () => print($content),
                $media->file_name,
                ['Content-Type' => $media->mime_type],
            );
        }
    }

    /**
     * Download report as Excel.
     */
    public function excel(Request $request, string $reportId): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $report = InventoryReport::where('organization_id', $request->user()->organization_id)
            ->findOrFail($reportId);

        if (! $report->excel_media_id) {
            $media = $this->reportService->generateExcel($report);
        } else {
            $media = $report->excelMedia;
        }

        // Try signed URL first (for S3), fall back to direct download
        try {
            $url = $this->storageService->getSignedUrl($media);

            return response()->json([
                'url' => $url,
                'file_name' => $media->file_name,
            ]);
        } catch (\RuntimeException $e) {
            $content = $this->storageService->getFileContents($media);
            if (! $content) {
                $media = $this->reportService->generateExcel($report);
                $content = $this->storageService->getFileContents($media);
            }

            return response()->streamDownload(
                fn () => print($content),
                $media->file_name,
                ['Content-Type' => $media->mime_type],
            );
        }
    }

    protected function formatReport(InventoryReport $report): array
    {
        return [
            'id' => $report->id,
            'type' => $report->type,
            'title' => $report->title,
            'summary' => $report->summary,
            'ai_summary' => $report->ai_summary,
            'data' => $report->data,
            'has_pdf' => (bool) $report->pdf_media_id,
            'has_excel' => (bool) $report->excel_media_id,
            'generated_by' => $report->generated_by,
            'created_at' => $report->created_at->toIso8601String(),
        ];
    }
}
