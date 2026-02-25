<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TaskReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        protected array $data,
    ) {}

    public function title(): string
    {
        return 'Rapport de tâche';
    }

    public function headings(): array
    {
        return [
            'Asset', 'Code', 'Catégorie', 'Statut', 'Condition',
            'Méthode', 'Scanné par', 'Scanné le', 'Notes',
        ];
    }

    public function array(): array
    {
        return $this->data['items']->map(fn ($item) => [
            $item->asset?->name ?? '—',
            $item->asset?->asset_code ?? '—',
            $item->asset?->category?->name ?? '—',
            $item->status->getLabel(),
            $item->condition?->name ?? '—',
            $item->identification_method ?? '—',
            $item->scanner?->name ?? '—',
            $item->scanned_at?->format('d/m/Y H:i') ?? '—',
            $item->condition_notes ?? '—',
        ])->toArray();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
