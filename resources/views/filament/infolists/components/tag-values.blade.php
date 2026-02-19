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

                @if($isWireless)
                    <div class="mt-1 rounded-lg bg-gray-50 dark:bg-gray-800/50 overflow-hidden">
                        <div class="flex items-center gap-3 p-3">
                            @if($encodingMode === 'nfc')
                                {{-- NFC contactless icon --}}
                                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                                    <svg class="w-7 h-7 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M6 12c0-3.3 2.7-6 6-6" stroke-linecap="round"/>
                                        <path d="M3 12c0-5 4-9 9-9" stroke-linecap="round"/>
                                        <path d="M18 12c0 3.3-2.7 6-6 6" stroke-linecap="round"/>
                                        <path d="M21 12c0 5-4 9-9 9" stroke-linecap="round"/>
                                        <circle cx="12" cy="12" r="2" fill="currentColor" stroke="none"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-medium text-blue-600 dark:text-blue-400">NFC Tag</span>
                            @else
                                {{-- RFID signal icon --}}
                                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900/40 flex items-center justify-center">
                                    <svg class="w-7 h-7 text-purple-600 dark:text-purple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="2" y="7" width="8" height="10" rx="1" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M10 10h4" stroke-linecap="round"/>
                                        <path d="M10 14h4" stroke-linecap="round"/>
                                        <path d="M16 8c1.1 1.1 1.8 2.5 1.8 4s-.7 2.9-1.8 4" stroke-linecap="round"/>
                                        <path d="M19 5.5c2 2 3.2 4.7 3.2 7.5s-1.2 5.5-3.2 7.5" stroke-linecap="round" opacity="0.6"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-medium text-purple-600 dark:text-purple-400">RFID Tag</span>
                            @endif
                        </div>
                        {{-- Info note --}}
                        <div class="px-3 pb-3">
                            @if($encodingMode === 'nfc')
                                <div class="rounded-xl border p-4 border-blue-500 bg-blue-50 dark:border-blue-500/30 dark:bg-blue-500/15">
                                    <div class="flex items-start gap-3">
                                        <div class="-mt-0.5 text-blue-500">
                                            <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M3.6501 11.9996C3.6501 7.38803 7.38852 3.64961 12.0001 3.64961C16.6117 3.64961 20.3501 7.38803 20.3501 11.9996C20.3501 16.6112 16.6117 20.3496 12.0001 20.3496C7.38852 20.3496 3.6501 16.6112 3.6501 11.9996ZM12.0001 1.84961C6.39441 1.84961 1.8501 6.39392 1.8501 11.9996C1.8501 17.6053 6.39441 22.1496 12.0001 22.1496C17.6058 22.1496 22.1501 17.6053 22.1501 11.9996C22.1501 6.39392 17.6058 1.84961 12.0001 1.84961ZM10.9992 7.52468C10.9992 8.07697 11.4469 8.52468 11.9992 8.52468H12.0002C12.5525 8.52468 13.0002 8.07697 13.0002 7.52468C13.0002 6.9724 12.5525 6.52468 12.0002 6.52468H11.9992C11.4469 6.52468 10.9992 6.9724 10.9992 7.52468ZM12.0002 17.371C11.586 17.371 11.2502 17.0352 11.2502 16.621V10.9445C11.2502 10.5303 11.586 10.1945 12.0002 10.1945C12.4144 10.1945 12.7502 10.5303 12.7502 10.9445V16.621C12.7502 17.0352 12.4144 17.371 12.0002 17.371Z" fill="currentColor"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="mb-1 text-sm font-semibold text-blue-800 dark:text-blue-300">NFC — HF 13.56 MHz</h4>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Scannable via un smartphone compatible NFC (Android / iPhone) ou un lecteur NFC dédié connecté en USB. Portée : quelques centimètres (contact).</p>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-xl border p-4 border-purple-500 bg-purple-50 dark:border-purple-500/30 dark:bg-purple-500/15">
                                    <div class="flex items-start gap-3">
                                        <div class="-mt-0.5 text-purple-500">
                                            <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M3.6501 11.9996C3.6501 7.38803 7.38852 3.64961 12.0001 3.64961C16.6117 3.64961 20.3501 7.38803 20.3501 11.9996C20.3501 16.6112 16.6117 20.3496 12.0001 20.3496C7.38852 20.3496 3.6501 16.6112 3.6501 11.9996ZM12.0001 1.84961C6.39441 1.84961 1.8501 6.39392 1.8501 11.9996C1.8501 17.6053 6.39441 22.1496 12.0001 22.1496C17.6058 22.1496 22.1501 17.6053 22.1501 11.9996C22.1501 6.39392 17.6058 1.84961 12.0001 1.84961ZM10.9992 7.52468C10.9992 8.07697 11.4469 8.52468 11.9992 8.52468H12.0002C12.5525 8.52468 13.0002 8.07697 13.0002 7.52468C13.0002 6.9724 12.5525 6.52468 12.0002 6.52468H11.9992C11.4469 6.52468 10.9992 6.9724 10.9992 7.52468ZM12.0002 17.371C11.586 17.371 11.2502 17.0352 11.2502 16.621V10.9445C11.2502 10.5303 11.586 10.1945 12.0002 10.1945C12.4144 10.1945 12.7502 10.5303 12.7502 10.9445V16.621C12.7502 17.0352 12.4144 17.371 12.0002 17.371Z" fill="currentColor"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="mb-1 text-sm font-semibold text-purple-800 dark:text-purple-300">RFID — UHF 860–960 MHz</h4>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Scannable via un lecteur RFID UHF dédié (pistolet, portique ou module USB). Portée longue distance : jusqu'à 10+ mètres selon le lecteur et l'environnement.</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif($bwipBcid)
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
