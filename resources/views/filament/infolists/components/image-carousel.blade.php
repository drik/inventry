@php
    $images = $getRecord()->images()->orderBy('sort_order')->get();
@endphp

@if($images->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400 italic">No images</p>
@else
    <div
        x-data="{
            current: 0,
            total: {{ $images->count() }},
            next() { this.current = (this.current + 1) % this.total },
            prev() { this.current = (this.current - 1 + this.total) % this.total },
        }"
        class="relative w-full max-w-2xl mx-auto"
    >
        {{-- Main image --}}
        <div class="relative overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800" style="aspect-ratio: 16/10;">
            @foreach($images as $index => $image)
                <div
                    x-show="current === {{ $index }}"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute inset-0 flex items-center justify-center"
                >
                    <img
                        src="{{ Storage::disk('public')->url($image->file_path) }}"
                        alt="{{ $image->caption ?? 'Asset image' }}"
                        class="max-h-full max-w-full object-contain"
                    />
                </div>
            @endforeach

            {{-- Navigation arrows --}}
            @if($images->count() > 1)
                <button
                    x-on:click="prev()"
                    class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 dark:bg-gray-900/80 p-2 shadow-md hover:bg-white dark:hover:bg-gray-900 transition"
                >
                    <x-heroicon-m-chevron-left class="h-5 w-5 text-gray-700 dark:text-gray-300" />
                </button>
                <button
                    x-on:click="next()"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 dark:bg-gray-900/80 p-2 shadow-md hover:bg-white dark:hover:bg-gray-900 transition"
                >
                    <x-heroicon-m-chevron-right class="h-5 w-5 text-gray-700 dark:text-gray-300" />
                </button>
            @endif
        </div>

        {{-- Caption --}}
        @foreach($images as $index => $image)
            <p
                x-show="current === {{ $index }}"
                class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400"
            >
                {{ $image->caption ?? '' }}
                @if($image->is_primary)
                    <span class="inline-flex items-center rounded-full bg-primary-50 dark:bg-primary-400/10 px-2 py-0.5 text-xs font-medium text-primary-700 dark:text-primary-400 ring-1 ring-inset ring-primary-600/20">Primary</span>
                @endif
            </p>
        @endforeach

        {{-- Dots --}}
        @if($images->count() > 1)
            <div class="mt-3 flex justify-center gap-2">
                @foreach($images as $index => $image)
                    <button
                        x-on:click="current = {{ $index }}"
                        :class="current === {{ $index }} ? 'bg-primary-500' : 'bg-gray-300 dark:bg-gray-600'"
                        class="h-2 w-2 rounded-full transition"
                    ></button>
                @endforeach
            </div>
        @endif

        {{-- Counter --}}
        <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">
            <span x-text="current + 1"></span> / {{ $images->count() }}
        </p>
    </div>
@endif
