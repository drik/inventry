@php
    $data = $this->getUsageData();
@endphp

@if (!empty($data))
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>Utilisation du plan</span>
                <x-filament::badge color="primary">{{ $data['plan_name'] }}</x-filament::badge>
            </div>
        </x-slot>

        <style>
            .plan-usage-grid { grid-template-columns: repeat(1, minmax(0, 1fr)); }
            @media (min-width: 640px) { .plan-usage-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            @media (min-width: 1024px) { .plan-usage-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
        </style>
        <div class="plan-usage-grid grid gap-4">
            @foreach ($data['features'] as $feature)
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <x-filament::icon :icon="$feature['icon']" class="h-5 w-5 shrink-0 text-gray-400" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate" title="{{ $feature['label'] }}">{{ $feature['label'] }}</span>
                    </div>

                    <div class="flex items-baseline gap-1 mb-2">
                        <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $feature['current'] }}</span>
                        <span class="text-sm text-gray-500">
                            @if ($feature['is_unlimited'])
                                / &infin;
                            @elseif ($feature['is_disabled'])
                                (désactivé)
                            @else
                                / {{ $feature['limit'] }}
                            @endif
                        </span>
                    </div>

                    @unless ($feature['is_disabled'] || $feature['is_unlimited'])
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full transition-all {{ $feature['percentage'] >= 90 ? 'bg-danger-500' : ($feature['percentage'] >= 70 ? 'bg-warning-500' : 'bg-primary-500') }}"
                                 style="width: {{ $feature['percentage'] }}%">
                            </div>
                        </div>
                    @endunless
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
@endif
