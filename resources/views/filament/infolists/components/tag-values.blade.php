@php
    $record = $getRecord();
    $tagValues = $record->tagValues()->with('tag')->get()->filter(fn ($tv) => filled($tv->value));

    $encodingModeMap = [
        'qr_code' => 'qrcode',
        'data_matrix' => 'datamatrix',
        'pdf417' => 'pdf417',
        'aztec' => 'azteccode',
        'ean_13' => 'ean13',
        'ean_8' => 'ean8',
        'upc_a' => 'upca',
        'code_128' => 'code128',
        'code_39' => 'code39',
        'itf' => 'interleaved2of5',
    ];

    $wirelessModes = ['rfid', 'nfc'];
@endphp

@if($tagValues->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400 italic">No identification tags.</p>
@else
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($tagValues as $tagValue)
            @php
                $encodingMode = $tagValue->encoding_mode?->value ?? $tagValue->tag?->encoding_mode?->value;
                $encodingLabel = $tagValue->encoding_mode?->getLabel() ?? $tagValue->tag?->encoding_mode?->getLabel();
                $bwipBcid = $encodingModeMap[$encodingMode] ?? null;
                $isWireless = in_array($encodingMode, $wirelessModes);
                $is2D = in_array($bwipBcid, ['qrcode', 'datamatrix', 'pdf417', 'azteccode']);
            @endphp
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                    {{ $tagValue->tag?->name ?? 'Tag' }}
                    @if($encodingLabel)
                        <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs text-gray-600 dark:text-gray-400">{{ $encodingLabel }}</span>
                    @endif
                </p>
                <p class="text-sm font-semibold text-gray-950 dark:text-white mb-2">{{ $tagValue->value }}</p>

                @if($bwipBcid)
                    <div
                        x-data="{
                            error: null,
                            rendered: false,
                            render() {
                                if (this.rendered) return;
                                if (typeof bwipjs === 'undefined') {
                                    this.error = 'Barcode library not loaded';
                                    return;
                                }
                                try {
                                    bwipjs.toCanvas(this.$refs.canvas, {
                                        bcid: '{{ $bwipBcid }}',
                                        text: {{ json_encode($tagValue->value) }},
                                        scale: {{ $is2D ? 3 : 2 }},
                                        @if(!$is2D)
                                        height: 10,
                                        includetext: true,
                                        textxalign: 'center',
                                        @endif
                                    });
                                    this.rendered = true;
                                } catch (e) {
                                    this.error = e.message || 'Unable to render barcode';
                                }
                            }
                        }"
                        x-init="
                            if (typeof bwipjs !== 'undefined') {
                                render();
                            } else {
                                const script = document.createElement('script');
                                script.src = 'https://cdn.jsdelivr.net/npm/bwip-js@4';
                                script.onload = () => render();
                                script.onerror = () => error = 'Failed to load barcode library';
                                document.head.appendChild(script);
                            }
                        "
                    >
                        <canvas x-ref="canvas" x-show="!error" class="max-w-full"></canvas>
                        <p x-show="error" x-text="error" class="text-xs text-red-500 mt-1"></p>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
