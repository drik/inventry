@php
    $record = $getRecord();
    $history = $record ? $record->statusHistory()->with('user')->latest('created_at')->get() : collect();
@endphp

<div class="space-y-3">
    @if($history->isEmpty())
        <div class="text-sm text-gray-500 dark:text-gray-400 italic py-4 text-center">
            No status history found.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Date</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">From</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">To</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Changed By</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Reason</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($history as $entry)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                {{ $entry->created_at?->format('M d, Y H:i') ?? '—' }}
                            </td>
                            <td class="px-3 py-2">
                                @if($entry->from_status)
                                    <x-filament::badge>{{ $entry->from_status->getLabel() }}</x-filament::badge>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <x-filament::badge>{{ $entry->to_status->getLabel() }}</x-filament::badge>
                            </td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                {{ $entry->user?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                {{ $entry->reason ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
