<div
    x-data="tagScanner()"
    @open-tag-scanner.window="openScanner($event.detail)"
>
    {{-- Full-screen modal overlay --}}
    <div
        x-show="isOpen"
        x-cloak
        @keydown.escape.window="if (isOpen) closeScanner()"
        style="position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.5);"
    >
        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; padding:16px;">
            <div @click.stop="" style="width:100%; max-width:440px; border-radius:16px; overflow:hidden;" class="bg-white dark:bg-gray-900">

                {{-- Header --}}
                <div style="padding:16px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid;" class="border-gray-200 dark:border-gray-700">
                    <h3 style="font-size:16px; font-weight:600; margin:0;" class="text-gray-900 dark:text-white" x-text="modalTitle"></h3>
                    <button @click="closeScanner()" type="button" style="background:none; border:none; cursor:pointer; padding:4px;" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div style="padding:20px;">

                    {{-- BARCODE MODE --}}
                    <div x-show="mode === 'barcode'" x-cloak>
                        <div id="tag-scanner-reader" wire:ignore class="tag-scanner-video-container"></div>
                        <div x-show="cameraError" style="margin-top:12px; padding:10px 14px; border-radius:8px;" class="bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400" style="font-size:14px;">
                            <p style="font-size:14px; margin:0;" x-text="cameraError"></p>
                        </div>
                        <p x-show="!cameraError" style="font-size:13px; text-align:center; margin:12px 0 0; color:#6b7280;">
                            Pointez la cam&eacute;ra vers le code-barres
                        </p>
                    </div>

                    {{-- NFC MODE --}}
                    <div x-show="mode === 'nfc'" x-cloak style="text-align:center; padding:24px 0;">
                        <div class="tag-nfc-zone" style="margin:0 auto;">
                            <div class="tag-nfc-ring tag-nfc-ring-1"></div>
                            <div class="tag-nfc-ring tag-nfc-ring-2"></div>
                            <div class="tag-nfc-ring tag-nfc-ring-3"></div>
                            <div class="tag-nfc-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424-7.424m-1.414 1.414a3 3 0 0 0-4.243 0m0 4.243a3 3 0 0 0 4.243 0m1.414 1.414a5.25 5.25 0 0 1-7.424 0M5.106 18.894c-3.808-3.808-3.808-9.98 0-13.788m13.788 0c3.808 3.808 3.808 9.98 0 13.788"/>
                                </svg>
                            </div>
                        </div>
                        <p style="font-size:14px; font-weight:500; margin-top:20px;" class="text-gray-700 dark:text-gray-300">Approchez le t&eacute;l&eacute;phone du tag NFC</p>
                        <p style="font-size:13px; margin-top:4px; color:#6b7280;">Placez le dos du t&eacute;l&eacute;phone contre le tag</p>
                        <div x-show="nfcError" style="margin-top:16px; padding:10px 14px; border-radius:8px;" class="bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400">
                            <p style="font-size:14px; margin:0;" x-text="nfcError"></p>
                        </div>
                    </div>

                    {{-- UNSUPPORTED MODE --}}
                    <div x-show="mode === 'unsupported'" x-cloak style="text-align:center; padding:24px 0;">
                        <div style="width:56px; height:56px; border-radius:9999px; display:flex; align-items:center; justify-content:center; margin:0 auto;" class="bg-amber-100 dark:bg-amber-900/30">
                            <svg xmlns="http://www.w3.org/2000/svg" style="width:28px; height:28px;" class="text-amber-600 dark:text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <p style="font-size:14px; margin-top:16px; color:#6b7280;" x-text="unsupportedMessage"></p>
                    </div>

                    {{-- NO ENCODING MODE --}}
                    <div x-show="mode === 'no_encoding'" x-cloak style="text-align:center; padding:24px 0;">
                        <div style="width:56px; height:56px; border-radius:9999px; display:flex; align-items:center; justify-content:center; margin:0 auto;" class="bg-blue-100 dark:bg-blue-900/30">
                            <svg xmlns="http://www.w3.org/2000/svg" style="width:28px; height:28px;" class="text-blue-600 dark:text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <p style="font-size:14px; margin-top:16px; color:#6b7280;">
                            S&eacute;lectionnez d'abord un mode d'encodage pour ce tag avant de scanner.
                        </p>
                    </div>
                </div>

                {{-- Footer --}}
                <div style="padding:12px 20px; text-align:right; border-top:1px solid;" class="border-gray-200 dark:border-gray-700">
                    <button
                        @click="closeScanner()"
                        type="button"
                        style="font-size:14px; font-weight:500; padding:8px 16px; border-radius:8px; cursor:pointer; border:1px solid;"
                        class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        x-text="mode === 'barcode' || mode === 'nfc' ? 'Annuler' : 'Fermer'"
                    ></button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .tag-scanner-video-container {
        width: 100%;
        min-height: 300px;
        background: #000;
        border-radius: 8px;
        overflow: hidden;
    }
    .tag-scanner-video-container video {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        border-radius: 0 !important;
    }
    #tag-scanner-reader img,
    #tag-scanner-reader > div:first-child > img,
    #tag-scanner-reader #qr-shaded-region {
        display: none !important;
    }
    .tag-nfc-zone {
        position: relative;
        width: 140px;
        height: 140px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .tag-nfc-ring {
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        border: 2px solid rgba(59, 130, 246, 0.3);
    }
    .tag-nfc-ring-1 { animation: tag-nfc-expand 2.5s ease-out 0s infinite; }
    .tag-nfc-ring-2 { animation: tag-nfc-expand 2.5s ease-out 0.8s infinite; }
    .tag-nfc-ring-3 { animation: tag-nfc-expand 2.5s ease-out 1.6s infinite; }
    .tag-nfc-center {
        position: relative;
        z-index: 2;
        color: #3b82f6;
        background: rgba(59, 130, 246, 0.1);
        width: 64px;
        height: 64px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid rgba(59, 130, 246, 0.3);
    }
    @keyframes tag-nfc-expand {
        0% { transform: scale(1); opacity: 0.6; border-color: rgba(59, 130, 246, 0.4); }
        100% { transform: scale(2); opacity: 0; border-color: rgba(59, 130, 246, 0); }
    }
</style>

@push('scripts')
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
@endpush

<script>
(function() {
    function registerComponent() {
        Alpine.data('tagScanner', () => ({
            isOpen: false,
            mode: null,
            scanner: null,
            nfcReader: null,
            nfcAbortController: null,
            targetStatePath: null,
            cameraError: null,
            nfcError: null,
            nfcSupported: 'NDEFReader' in window,
            unsupportedMessage: '',

            barcodeModes: [
                'qr_code', 'data_matrix', 'pdf417', 'aztec',
                'ean_13', 'ean_8', 'upc_a', 'code_128', 'code_39', 'itf',
            ],

            get modalTitle() {
                switch (this.mode) {
                    case 'barcode': return 'Scanner un code-barres';
                    case 'nfc': return 'Scanner un tag NFC';
                    case 'unsupported': return 'Scan non disponible';
                    case 'no_encoding': return 'Encodage requis';
                    default: return 'Scanner';
                }
            },

            init() {
                this.$watch('isOpen', value => {
                    document.body.style.overflow = value ? 'hidden' : '';
                });
            },

            openScanner(detail) {
                const { encodingMode, statePath } = detail;
                this.targetStatePath = statePath;
                this.cameraError = null;
                this.nfcError = null;

                if (!encodingMode) {
                    this.mode = 'no_encoding';
                    this.isOpen = true;
                    return;
                }

                if (this.barcodeModes.includes(encodingMode)) {
                    this.mode = 'barcode';
                    this.isOpen = true;
                    this.$nextTick(() => this.startBarcodeScanner());
                    return;
                }

                if (encodingMode === 'nfc') {
                    if (this.nfcSupported) {
                        this.mode = 'nfc';
                        this.isOpen = true;
                        this.startNfcScanner();
                    } else {
                        this.mode = 'unsupported';
                        this.unsupportedMessage = 'Le scan NFC n\'est pas supporté par ce navigateur. Utilisez Chrome sur Android.';
                        this.isOpen = true;
                    }
                    return;
                }

                if (encodingMode === 'rfid') {
                    this.mode = 'unsupported';
                    this.unsupportedMessage = 'Le scan RFID nécessite un lecteur spécialisé connecté via USB ou Bluetooth.';
                    this.isOpen = true;
                    return;
                }

                this.mode = 'unsupported';
                this.unsupportedMessage = 'Le scan n\'est pas disponible pour ce type d\'encodage.';
                this.isOpen = true;
            },

            async startBarcodeScanner() {
                if (typeof Html5Qrcode === 'undefined') {
                    this.cameraError = 'Librairie de scan non chargée. Rechargez la page.';
                    return;
                }
                try {
                    this.scanner = new Html5Qrcode('tag-scanner-reader');
                    await this.scanner.start(
                        { facingMode: 'environment' },
                        { fps: 10, qrbox: { width: 240, height: 240 }, disableFlip: false },
                        (decodedText) => this.onScanSuccess(decodedText),
                        () => {}
                    );
                } catch (err) {
                    const errStr = err.toString();
                    if (errStr.includes('NotAllowedError')) {
                        this.cameraError = 'Accès à la caméra refusé. Autorisez l\'accès dans les paramètres du navigateur.';
                    } else if (errStr.includes('NotFoundError')) {
                        this.cameraError = 'Aucune caméra détectée sur cet appareil.';
                    } else if (errStr.includes('secure') || errStr.includes('HTTPS')) {
                        this.cameraError = 'La caméra nécessite une connexion HTTPS.';
                    } else {
                        this.cameraError = 'Erreur caméra : ' + errStr;
                    }
                }
            },

            async startNfcScanner() {
                try {
                    this.nfcReader = new NDEFReader();
                    this.nfcAbortController = new AbortController();
                    this.nfcReader.addEventListener('reading', (event) => {
                        const code = this.extractNfcValue(event);
                        if (code) this.onScanSuccess(code);
                    });
                    this.nfcReader.addEventListener('readingerror', () => {
                        this.nfcError = 'Tag NFC détecté mais non lisible (format non NDEF).';
                    });
                    await this.nfcReader.scan({ signal: this.nfcAbortController.signal });
                } catch (err) {
                    const name = err.name || '';
                    if (name === 'NotAllowedError') {
                        this.nfcError = 'Permission NFC refusée. Autorisez dans les paramètres.';
                    } else if (name === 'NotSupportedError') {
                        this.nfcError = 'NFC non disponible sur cet appareil.';
                    } else if (name !== 'AbortError') {
                        this.nfcError = 'Erreur NFC : ' + (err.message || err);
                    }
                }
            },

            extractNfcValue(event) {
                const decoder = new TextDecoder();
                if (event.message && event.message.records) {
                    for (let record of event.message.records) {
                        if (record.recordType === 'text') {
                            try {
                                const data = new Uint8Array(record.data.buffer || record.data);
                                const langLen = data[0] & 0x3F;
                                const text = decoder.decode(data.slice(1 + langLen));
                                if (text && text.trim()) return text.trim();
                            } catch(e) {}
                        }
                        if (record.recordType === 'url') {
                            try {
                                const text = decoder.decode(record.data);
                                if (text && text.trim()) return text.trim();
                            } catch(e) {}
                        }
                        if (record.recordType === 'mime' && record.mediaType === 'application/json') {
                            try {
                                const json = JSON.parse(decoder.decode(record.data));
                                if (json.code || json.id || json.value) return json.code || json.id || json.value;
                            } catch(e) {}
                        }
                    }
                }
                if (event.serialNumber && event.serialNumber !== '') return event.serialNumber;
                return null;
            },

            onScanSuccess(value) {
                if (this.targetStatePath && this.$wire) {
                    this.$wire.set(this.targetStatePath, value);
                }
                this.closeScanner();
            },

            async closeScanner() {
                if (this.scanner) {
                    try { await this.scanner.stop(); } catch(e) {}
                    this.scanner = null;
                }
                const container = document.getElementById('tag-scanner-reader');
                if (container) {
                    container.querySelectorAll('video').forEach(v => {
                        if (v.srcObject) { v.srcObject.getTracks().forEach(t => t.stop()); v.srcObject = null; }
                    });
                    container.innerHTML = '';
                }
                if (this.nfcAbortController) {
                    try { this.nfcAbortController.abort(); } catch(e) {}
                    this.nfcAbortController = null;
                }
                this.nfcReader = null;
                this.isOpen = false;
                this.mode = null;
                this.targetStatePath = null;
                this.cameraError = null;
                this.nfcError = null;
            },
        }));
    }

    if (window.Alpine) {
        registerComponent();
    } else {
        document.addEventListener('alpine:init', registerComponent);
    }
})();
</script>
