@php
    $image = $getRecord()->primaryImage;
    $url = $image ? Storage::disk('public')->url($image->file_path) : null;
@endphp

@if($url)
    <div x-data="{ open: false }" class="relative">
        {{-- Thumbnail --}}
        <img
            src="{{ $url }}"
            alt="{{ $getRecord()->name }}"
            class="h-[200px] w-full rounded-lg object-cover cursor-pointer ring-1 ring-gray-200 dark:ring-gray-700 hover:ring-primary-500 hover:shadow-lg transition duration-200"
            x-on:click="open = true"
        />

        {{-- Lightbox overlay --}}
        <template x-teleport="body">
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:click="open = false"
                x-on:keydown.escape.window="open = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                style="display: none;"
            >
                {{-- Close button --}}
                <button
                    x-on:click="open = false"
                    class="absolute top-4 right-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition"
                >
                    <x-heroicon-m-x-mark class="h-6 w-6" />
                </button>

                {{-- Full-size image --}}
                <img
                    src="{{ $url }}"
                    alt="{{ $getRecord()->name }}"
                    class="max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                    x-on:click.stop
                />
            </div>
        </template>
    </div>
@else
    <div class="flex h-[200px] w-full items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
        <x-heroicon-o-photo class="h-12 w-12 text-gray-400 dark:text-gray-500" />
    </div>
@endif
