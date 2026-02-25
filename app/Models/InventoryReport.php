<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReport extends Model
{
    use BelongsToOrganization, HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'session_id',
        'task_id',
        'type',
        'title',
        'summary',
        'ai_summary',
        'data',
        'generated_by',
        'pdf_media_id',
        'excel_media_id',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class, 'session_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(InventoryTask::class, 'task_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function pdfMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'pdf_media_id');
    }

    public function excelMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'excel_media_id');
    }
}
