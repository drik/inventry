<?php

namespace App\Filament\App\Pages;

use App\Enums\InventorySessionStatus;
use App\Models\InventoryTask;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class MyInventoryTasks extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'My Scan Tasks';

    protected static ?string $title = 'My Inventory Scan Tasks';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.app.pages.my-inventory-tasks';

    public string $filter = 'active';

    #[Computed]
    public function tasks()
    {
        $query = InventoryTask::with(['session.items.asset', 'location'])
            ->where('assigned_to', Auth::id())
            ->whereHas('session', fn ($q) => $q->where('status', InventorySessionStatus::InProgress));

        if ($this->filter === 'active') {
            $query->whereIn('status', ['pending', 'in_progress']);
        } elseif ($this->filter === 'completed') {
            $query->where('status', 'completed');
        }

        return $query->latest()->get();
    }

    #[Computed]
    public function taskCounts(): array
    {
        $base = InventoryTask::where('assigned_to', Auth::id())
            ->whereHas('session', fn ($q) => $q->where('status', InventorySessionStatus::InProgress));

        return [
            'active' => (clone $base)->whereIn('status', ['pending', 'in_progress'])->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'all' => (clone $base)->count(),
        ];
    }
}
