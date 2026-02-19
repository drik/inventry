<x-filament-panels::page>
    @push('scripts')
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
        <script>
            // Auto-detect mobile and redirect to mobile scanner
            (function() {
                var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
                    || (window.innerWidth <= 768 && 'ontouchstart' in window);
                if (isMobile) {
                    var mobileUrl = window.location.href.replace('/execute-task/', '/execute-task-mobile/');
                    window.location.replace(mobileUrl);
                }
            })();
        </script>
    @endpush

    {{-- HEADER: Task info + Progress --}}
    <div class="mb-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $this->record->name }}
                </h2>
                <div class="mt-1 flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                    @if($task->location)
                        <span class="flex items-center gap-1">
                            <x-heroicon-o-map-pin class="h-4 w-4" />
                            {{ $task->location->name }}
                        </span>
                    @endif
                    <span @class([
                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-400' => $task->status === 'in_progress',
                        'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' => $task->status === 'completed',
                    ])>
                        {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                    </span>
                </div>
            </div>
            <div class="text-right">
                <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                    {{ $this->stats['scanned'] }} / {{ $this->stats['expected'] }}
                </span>
                <p class="text-sm text-gray-500 dark:text-gray-400">scanned</p>
            </div>
        </div>
        <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div
                class="h-3 rounded-full bg-primary-500 transition-all duration-500"
                style="width: {{ $this->stats['progress'] }}%"
            ></div>
        </div>
    </div>

    {{-- MAIN SPLIT LAYOUT --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

        {{-- LEFT PANEL --}}
        <div class="lg:col-span-3 space-y-4">

            {{-- Scanner Input --}}
            <div
                class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                x-data="{
                    scanning: false,
                    scanner: null,
                    cameraError: null,
                    async startScanner() {
                        this.cameraError = null;
                        this.scanning = true;
                        await this.$nextTick();
                        this.scanner = new Html5Qrcode('qr-reader-task');
                        try {
                            await this.scanner.start(
                                { facingMode: 'environment' },
                                { fps: 10, qrbox: { width: 250, height: 250 } },
                                (decodedText) => {
                                    this.stopScanner();
                                    $wire.set('barcode', decodedText);
                                    $wire.scanBarcode();
                                },
                                () => {}
                            );
                        } catch (err) {
                            this.scanning = false;
                            if (err.toString().includes('NotAllowedError') || err.toString().includes('secure')) {
                                this.cameraError = 'Camera access requires HTTPS. On Chrome: go to chrome://flags/#unsafely-treat-insecure-origin-as-secure and add this site URL.';
                            } else {
                                this.cameraError = 'Camera error: ' + err;
                            }
                            console.error('Camera error:', err);
                        }
                    },
                    async stopScanner() {
                        if (this.scanner) {
                            try { await this.scanner.stop(); } catch(e) {}
                            this.scanner = null;
                        }
                        this.scanning = false;
                    }
                }"
            >
                <div class="flex gap-2">
                    <input
                        type="text"
                        wire:model="barcode"
                        wire:keydown.enter="scanBarcode"
                        x-ref="barcodeInput"
                        x-on:barcode-processed.window="$nextTick(() => $refs.barcodeInput.focus())"
                        placeholder="Scan barcode or type asset code..."
                        class="fi-input block w-full rounded-lg border-gray-300 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white sm:text-sm"
                        autofocus
                    />
                    <button
                        x-on:click="scanning ? stopScanner() : startScanner()"
                        :style="scanning ? 'background-color: #dc2626' : 'background-color: #374151'"
                        class="inline-flex items-center rounded-lg px-3 py-2 text-white shadow-sm transition"
                        type="button"
                    >
                        <x-heroicon-o-camera class="h-5 w-5" />
                    </button>
                    <button
                        wire:click="scanBarcode"
                        style="background-color: #16a34a"
                        class="inline-flex items-center rounded-lg px-3 py-2 text-white shadow-sm transition"
                        type="button"
                    >
                        <x-heroicon-o-magnifying-glass class="h-5 w-5" />
                    </button>
                </div>

                {{-- Camera preview --}}
                <div x-show="scanning" x-transition x-cloak class="mt-4">
                    <div id="qr-reader-task" class="mx-auto max-w-sm overflow-hidden rounded-lg"></div>
                    <button
                        x-on:click="stopScanner()"
                        class="mt-2 text-sm text-gray-500 underline hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        type="button"
                    >
                        Close Camera
                    </button>
                </div>

                {{-- Camera Error --}}
                <div x-show="cameraError" x-transition x-cloak class="mt-3 rounded-lg p-3 text-sm font-medium" style="background-color: #fef2f2; color: #b91c1c;">
                    <span x-text="cameraError"></span>
                </div>

                {{-- Scan Feedback --}}
                @if($scanFeedback)
                    <div @class([
                        'mt-3 rounded-lg p-3 text-sm font-medium',
                        'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400' => $scanFeedbackType === 'success',
                        'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-400' => $scanFeedbackType === 'warning',
                        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400' => $scanFeedbackType === 'danger',
                    ])>
                        {{ $scanFeedback }}
                        @if($lastScannedAsset && ($lastScannedAsset['is_unexpected'] ?? false))
                            <button
                                wire:click="addUnexpected('{{ $lastScannedAsset['id'] }}')"
                                class="ml-2 rounded bg-yellow-600 px-2 py-1 text-xs font-semibold text-white hover:bg-yellow-700"
                                type="button"
                            >
                                Add as unexpected
                            </button>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Filter Tabs --}}
            <div class="flex gap-1 overflow-x-auto rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                @foreach(['all' => 'All', 'expected' => 'Expected', 'found' => 'Found', 'missing' => 'Missing', 'unexpected' => 'Unexpected'] as $tab => $label)
                    <button
                        wire:click="$set('activeTab', '{{ $tab }}')"
                        @class([
                            'whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition',
                            'bg-white text-gray-900 shadow dark:bg-gray-700 dark:text-white' => $activeTab === $tab,
                            'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $activeTab !== $tab,
                        ])
                        type="button"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Items Table --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 dark:border-white/5 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Asset Code</th>
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Name</th>
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Location</th>
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Status</th>
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Scanned</th>
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @forelse($this->items as $item)
                                <tr wire:key="item-{{ $item->id }}" class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-900 dark:text-white">
                                        {{ $item->asset?->asset_code ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">
                                        {{ $item->asset?->name ?? 'Unknown' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                        {{ $item->asset?->location?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-filament::badge :color="$item->status->getColor()" :icon="$item->status->getIcon()">
                                            {{ $item->status->getLabel() }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $item->scanned_at?->diffForHumans() ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-1">
                                            @if(in_array($item->status, [\App\Enums\InventoryItemStatus::Expected, \App\Enums\InventoryItemStatus::Missing]))
                                                <button
                                                    wire:click="markItemFound('{{ $item->id }}')"
                                                    class="rounded bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-700"
                                                    type="button"
                                                >
                                                    Found
                                                </button>
                                            @endif
                                            @if(in_array($item->status, [\App\Enums\InventoryItemStatus::Expected, \App\Enums\InventoryItemStatus::Found]))
                                                <button
                                                    wire:click="markItemMissing('{{ $item->id }}')"
                                                    class="rounded bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700"
                                                    type="button"
                                                >
                                                    Missing
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No items found for this filter.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- RIGHT PANEL --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Stats Cards --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Expected</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['expected'] }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Found</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->stats['found'] }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Missing</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->stats['missing'] }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Unexpected</p>
                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $this->stats['unexpected'] }}</p>
                </div>
            </div>

            {{-- Last Scanned Card --}}
            @if($lastScannedAsset && !($lastScannedAsset['is_unexpected'] ?? false))
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Last Scanned</h3>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $lastScannedAsset['asset_code'] }}</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $lastScannedAsset['name'] }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $lastScannedAsset['location'] ?? '—' }}
                        &bull;
                        {{ $lastScannedAsset['category'] ?? '—' }}
                    </p>
                </div>
            @endif

            {{-- Complete Task --}}
            @if($task->status !== 'completed')
                <button
                    wire:click="completeTask"
                    wire:confirm="Mark this task as completed?"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-green-600 px-4 py-3 font-medium text-white shadow-sm transition hover:bg-green-700"
                    type="button"
                >
                    <x-heroicon-o-check-circle class="h-5 w-5" />
                    Complete Task
                </button>
            @endif

            {{-- Mode Mobile --}}
            <a
                href="{{ \App\Filament\App\Resources\InventorySessionResource::getUrl('execute-task-mobile', ['record' => $this->record->id, 'taskId' => $task->id]) }}"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 font-medium text-white transition hover:bg-blue-700"
            >
                <x-heroicon-o-device-phone-mobile class="h-5 w-5" />
                Mode Mobile
            </a>

            {{-- Back to My Tasks --}}
            <a
                href="{{ route('filament.app.pages.my-inventory-tasks', ['tenant' => \Filament\Facades\Filament::getTenant()]) }}"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-gray-200 px-4 py-3 font-medium text-gray-700 transition hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                <x-heroicon-o-arrow-left class="h-5 w-5" />
                Back to My Tasks
            </a>
        </div>
    </div>
</x-filament-panels::page>
