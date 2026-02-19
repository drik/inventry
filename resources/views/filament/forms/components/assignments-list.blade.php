@php
    $record = $getRecord();
    $assignments = $record ? $record->assignments()->with(['assignee', 'assignedBy'])->latest('assigned_at')->get() : collect();
@endphp

<div class="space-y-3">
    @if($assignments->isEmpty())
        <div class="text-sm text-gray-500 dark:text-gray-400 italic py-4 text-center">
            No assignments found.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Assignee</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Type</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Assigned At</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Expected Return</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Returned At</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Assigned By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($assignments as $assignment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                                {{ $assignment->assignee?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-2">
                                @php
                                    $type = class_basename($assignment->assignee_type);
                                    $color = match($assignment->assignee_type) {
                                        'App\Models\User' => 'info',
                                        'App\Models\Department' => 'warning',
                                        'App\Models\Location' => 'success',
                                        default => 'gray',
                                    };
                                @endphp
                                <x-filament::badge :color="$color">{{ $type }}</x-filament::badge>
                            </td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                {{ $assignment->assigned_at?->format('M d, Y H:i') ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                @if($assignment->expected_return_at)
                                    <span @class(['text-danger-600 dark:text-danger-400' => $assignment->expected_return_at->isPast() && !$assignment->returned_at])>
                                        {{ $assignment->expected_return_at->format('M d, Y') }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($assignment->returned_at)
                                    <x-filament::badge color="success">{{ $assignment->returned_at->format('M d, Y H:i') }}</x-filament::badge>
                                @else
                                    <x-filament::badge color="warning">Active</x-filament::badge>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                {{ $assignment->assignedBy?->name ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
