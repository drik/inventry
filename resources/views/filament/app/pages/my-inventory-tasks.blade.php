<x-filament-panels::page>
    {{-- Filter Tabs --}}
    <div class="flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
        @foreach(['active' => 'Active', 'completed' => 'Completed', 'all' => 'All'] as $tab => $label)
            <button
                wire:click="$set('filter', '{{ $tab }}')"
                @class([
                    'whitespace-nowrap rounded-md px-4 py-2 text-sm font-medium transition',
                    'bg-white text-gray-900 shadow dark:bg-gray-700 dark:text-white' => $filter === $tab,
                    'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $filter !== $tab,
                ])
                type="button"
            >
                {{ $label }}
                <span @class([
                    'ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                    'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-400' => $filter === $tab,
                    'bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300' => $filter !== $tab,
                ])>
                    {{ $this->taskCounts[$tab] }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- Tasks Grid --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse($this->tasks as $task)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                {{-- Session Name --}}
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            {{ $task->session->name }}
                        </h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Session #{{ Str::limit($task->session_id, 8) }}
                        </p>
                    </div>
                    <span @class([
                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => $task->status === 'pending',
                        'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-400' => $task->status === 'in_progress',
                        'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' => $task->status === 'completed',
                    ])>
                        {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                    </span>
                </div>

                {{-- Location --}}
                @if($task->location)
                    <div class="mb-3 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <x-heroicon-o-map-pin class="h-4 w-4" />
                        {{ $task->location->name }}
                    </div>
                @endif

                {{-- Progress --}}
                @php
                    $sessionItems = $task->session->items;
                    $locationItems = $task->location_id
                        ? $sessionItems->filter(fn ($item) => $item->asset?->location_id === $task->location_id)
                        : $sessionItems;
                    $totalItems = $locationItems->count();
                    $scannedItems = $locationItems->filter(fn ($item) => $item->scanned_at !== null)->count();
                    $progress = $totalItems > 0 ? round(($scannedItems / $totalItems) * 100) : 0;
                @endphp

                <div class="mb-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Progress</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $scannedItems }} / {{ $totalItems }}</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            class="h-2 rounded-full transition-all duration-500 {{ $progress === 100 ? 'bg-green-500' : 'bg-primary-500' }}"
                            style="width: {{ $progress }}%"
                        ></div>
                    </div>
                </div>

                {{-- Times --}}
                <div class="mb-4 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                    @if($task->started_at)
                        <p>Started {{ $task->started_at->diffForHumans() }}</p>
                    @endif
                    @if($task->completed_at)
                        <p>Completed {{ $task->completed_at->diffForHumans() }}</p>
                    @endif
                </div>

                {{-- Action Button --}}
                @if($task->status !== 'completed')
                    <a
                        href="{{ \App\Filament\App\Resources\InventorySessionResource::getUrl('execute-task', ['record' => $task->session_id, 'taskId' => $task->id]) }}"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700"
                    >
                        <x-heroicon-o-qr-code class="h-4 w-4" />
                        {{ $task->status === 'pending' ? 'Start Scanning' : 'Continue Scanning' }}
                    </a>
                @else
                    <div class="flex w-full items-center justify-center gap-2 rounded-lg bg-green-100 px-4 py-2.5 text-sm font-medium text-green-700 dark:bg-green-500/20 dark:text-green-400">
                        <x-heroicon-o-check-circle class="h-4 w-4" />
                        Completed
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full rounded-xl bg-white p-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-heroicon-o-clipboard-document-list class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No tasks</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($filter === 'active')
                        You have no active inventory tasks assigned to you.
                    @elseif($filter === 'completed')
                        You haven't completed any tasks yet.
                    @else
                        No inventory tasks found.
                    @endif
                </p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
