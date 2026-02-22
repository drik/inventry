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

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-4">
            @foreach ($data['features'] as $feature)
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <x-filament::icon :icon="$feature['icon']" class="h-5 w-5 text-gray-400" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $feature['label'] }}</span>
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
