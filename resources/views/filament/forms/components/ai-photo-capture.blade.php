<div
    x-data="aiPhotoCapture()"
    @open-ai-photo-capture.window="openModal($event.detail)"
>
    {{-- Full-screen modal overlay --}}
    <div
        x-show="isOpen"
        x-cloak
        @keydown.escape.window="if (isOpen && !isAnalyzing) closeModal()"
        style="position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.5);"
    >
        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; padding:16px;">
            <div @click.stop="" style="width:100%; max-width:520px; border-radius:16px; overflow:hidden; max-height:90vh; display:flex; flex-direction:column;" class="bg-white dark:bg-gray-900">

                {{-- Header --}}
                <div style="padding:16px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid;" class="border-gray-200 dark:border-gray-700">
                    <h3 style="font-size:16px; font-weight:600; margin:0;" class="text-gray-900 dark:text-white">
                        Analyser avec l'IA
                    </h3>
                    <button @click="closeModal()" :disabled="isAnalyzing" type="button" style="background:none; border:none; cursor:pointer; padding:4px;" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div style="padding:20px; overflow-y:auto; flex:1;">

                    {{-- Mode Tabs --}}
                    <div x-show="!capturedImage" style="display:flex; gap:8px; margin-bottom:16px;">
                        <button @click="switchTab('upload')" type="button"
                            :class="activeTab === 'upload' ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 border-primary-300 dark:border-primary-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
                            style="flex:1; padding:10px 16px; border-radius:8px; font-size:14px; font-weight:500; cursor:pointer; border:1px solid; display:flex; align-items:center; justify-content:center; gap:6px;"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                            </svg>
                            Upload
                        </button>
                        <button @click="switchTab('camera')" type="button"
                            :class="activeTab === 'camera' ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 border-primary-300 dark:border-primary-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
                            style="flex:1; padding:10px 16px; border-radius:8px; font-size:14px; font-weight:500; cursor:pointer; border:1px solid; display:flex; align-items:center; justify-content:center; gap:6px;"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                            </svg>
                            Cam&eacute;ra
                        </button>
                    </div>

                    {{-- Upload Mode --}}
                    <div x-show="activeTab === 'upload' && !capturedImage" x-cloak>
                        <div
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop($event)"
                            :class="isDragging ? 'border-primary-400 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600'"
                            style="border:2px dashed; border-radius:12px; padding:40px 20px; text-align:center; cursor:pointer; transition:all 0.2s;"
                            @click="$refs.fileInput.click()"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" style="margin:0 auto;" class="text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/>
                            </svg>
                            <p style="font-size:14px; margin-top:12px; font-weight:500;" class="text-gray-700 dark:text-gray-300">
                                Glissez une image ici ou cliquez pour s&eacute;lectionner
                            </p>
                            <p style="font-size:12px; margin-top:4px;" class="text-gray-500">
                                JPEG, PNG — Max 2 Mo
                            </p>
                        </div>
                        <input
                            x-ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/jpg"
                            @change="handleFileSelect($event)"
                            style="display:none;"
                        >
                    </div>

                    {{-- Camera Mode --}}
                    <div x-show="activeTab === 'camera' && !capturedImage" x-cloak>
                        <div style="position:relative; border-radius:12px; overflow:hidden; background:#000;">
                            <video
                                x-ref="videoPreview"
                                autoplay
                                playsinline
                                muted
                                style="width:100%; display:block; min-height:300px; object-fit:cover;"
                            ></video>
                            <div x-show="!cameraReady && !cameraError" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;">
                                <div style="text-align:center;">
                                    <svg style="width:32px; height:32px; animation:spin 1s linear infinite; margin:0 auto;" class="text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p style="font-size:13px; margin-top:8px; color:white;">D&eacute;marrage de la cam&eacute;ra...</p>
                                </div>
                            </div>
                        </div>

                        <div x-show="cameraError" style="margin-top:12px; padding:10px 14px; border-radius:8px;" class="bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400">
                            <p style="font-size:14px; margin:0;" x-text="cameraError"></p>
                        </div>

                        <button
                            x-show="cameraReady"
                            @click="captureFromCamera()"
                            type="button"
                            style="width:100%; margin-top:12px; padding:10px 16px; border-radius:8px; font-size:14px; font-weight:500; cursor:pointer; border:none; display:flex; align-items:center; justify-content:center; gap:6px;"
                            class="bg-primary-600 text-white hover:bg-primary-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                            </svg>
                            Capturer
                        </button>
                    </div>

                    {{-- Preview (captured image) --}}
                    <div x-show="capturedImage" x-cloak>
                        <div style="position:relative; border-radius:12px; overflow:hidden;">
                            <img :src="capturedImage" style="width:100%; display:block; border-radius:12px;" alt="Captured">
                        </div>
                        <button
                            x-show="!isAnalyzing"
                            @click="resetCapture()"
                            type="button"
                            style="width:100%; margin-top:8px; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:500; cursor:pointer; border:1px solid;"
                            class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            Reprendre la photo
                        </button>
                    </div>

                    {{-- Error message --}}
                    <div x-show="errorMessage" x-cloak style="margin-top:12px; padding:10px 14px; border-radius:8px;" class="bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400">
                        <p style="font-size:14px; margin:0;" x-text="errorMessage"></p>
                    </div>

                    {{-- Analyzing state --}}
                    <div x-show="isAnalyzing" x-cloak style="margin-top:16px; text-align:center; padding:16px 0;">
                        <svg style="width:32px; height:32px; animation:spin 1s linear infinite; margin:0 auto;" class="text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p style="font-size:14px; margin-top:8px; font-weight:500;" class="text-gray-700 dark:text-gray-300">
                            Analyse en cours...
                        </p>
                        <p style="font-size:12px; margin-top:4px;" class="text-gray-500">
                            L'IA identifie les informations du produit
                        </p>
                    </div>

                </div>

                {{-- Footer --}}
                <div style="padding:12px 20px; display:flex; gap:8px; justify-content:flex-end; border-top:1px solid;" class="border-gray-200 dark:border-gray-700">
                    <button
                        @click="closeModal()"
                        :disabled="isAnalyzing"
                        type="button"
                        style="font-size:14px; font-weight:500; padding:8px 16px; border-radius:8px; cursor:pointer; border:1px solid;"
                        class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                    >
                        Annuler
                    </button>
                    <button
                        x-show="capturedImage && !isAnalyzing"
                        @click="analyzePhoto()"
                        type="button"
                        style="font-size:14px; font-weight:500; padding:8px 16px; border-radius:8px; cursor:pointer; border:none; display:flex; align-items:center; gap:6px;"
                        class="bg-primary-600 text-white hover:bg-primary-700"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"/>
                        </svg>
                        Analyser avec l'IA
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Hidden canvas for camera capture --}}
    <canvas x-ref="captureCanvas" style="display:none;"></canvas>
</div>

<style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>

<script>
(function() {
    function registerComponent() {
        Alpine.data('aiPhotoCapture', () => ({
            isOpen: false,
            activeTab: 'upload',
            capturedImage: null,
            base64Data: null,
            cameraReady: false,
            cameraError: null,
            errorMessage: null,
            isAnalyzing: false,
            isDragging: false,
            stream: null,
            callbackMethod: null,

            init() {
                this.$watch('isOpen', value => {
                    document.body.style.overflow = value ? 'hidden' : '';
                });
            },

            openModal(detail) {
                this.callbackMethod = detail?.method || 'analyzePhotoForAsset';
                this.isOpen = true;
                this.resetState();
            },

            resetState() {
                this.capturedImage = null;
                this.base64Data = null;
                this.cameraReady = false;
                this.cameraError = null;
                this.errorMessage = null;
                this.isAnalyzing = false;
                this.isDragging = false;
                this.stopCamera();
            },

            switchTab(tab) {
                if (this.isAnalyzing) return;
                this.stopCamera();
                this.activeTab = tab;
                this.cameraError = null;
                if (tab === 'camera') {
                    this.$nextTick(() => this.startCamera());
                }
            },

            async startCamera() {
                this.cameraReady = false;
                this.cameraError = null;
                try {
                    // Try rear camera first (mobile), fallback to any camera
                    let constraints = { video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 960 } } };
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                    } catch (e) {
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: true });
                    }
                    this.$refs.videoPreview.srcObject = this.stream;
                    this.$refs.videoPreview.onloadeddata = () => {
                        this.cameraReady = true;
                    };
                } catch (err) {
                    const errStr = err.toString();
                    if (errStr.includes('NotAllowedError')) {
                        this.cameraError = "Acc\u00e8s \u00e0 la cam\u00e9ra refus\u00e9. Autorisez l'acc\u00e8s dans les param\u00e8tres du navigateur.";
                    } else if (errStr.includes('NotFoundError')) {
                        this.cameraError = "Aucune cam\u00e9ra d\u00e9tect\u00e9e sur cet appareil.";
                    } else if (errStr.includes('secure') || errStr.includes('HTTPS')) {
                        this.cameraError = "La cam\u00e9ra n\u00e9cessite une connexion HTTPS.";
                    } else {
                        this.cameraError = "Erreur cam\u00e9ra : " + errStr;
                    }
                }
            },

            stopCamera() {
                if (this.stream) {
                    this.stream.getTracks().forEach(t => t.stop());
                    this.stream = null;
                }
                if (this.$refs.videoPreview) {
                    this.$refs.videoPreview.srcObject = null;
                }
                this.cameraReady = false;
            },

            captureFromCamera() {
                const video = this.$refs.videoPreview;
                const canvas = this.$refs.captureCanvas;
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0);
                this.capturedImage = canvas.toDataURL('image/jpeg', 0.85);
                this.base64Data = this.capturedImage;
                this.stopCamera();
            },

            handleFileSelect(event) {
                const file = event.target.files[0];
                if (file) this.processFile(file);
                event.target.value = '';
            },

            handleDrop(event) {
                this.isDragging = false;
                const file = event.dataTransfer.files[0];
                if (file) this.processFile(file);
            },

            processFile(file) {
                if (!file.type.match(/^image\/(jpeg|jpg|png)$/)) {
                    this.errorMessage = "Format non support\u00e9. Utilisez JPEG ou PNG.";
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    this.errorMessage = "L'image est trop volumineuse (max 2 Mo).";
                    return;
                }
                this.errorMessage = null;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.capturedImage = e.target.result;
                    this.base64Data = e.target.result;
                };
                reader.readAsDataURL(file);
            },

            resetCapture() {
                this.capturedImage = null;
                this.base64Data = null;
                this.errorMessage = null;
                if (this.activeTab === 'camera') {
                    this.$nextTick(() => this.startCamera());
                }
            },

            async analyzePhoto() {
                if (!this.base64Data || this.isAnalyzing) return;
                this.isAnalyzing = true;
                this.errorMessage = null;

                try {
                    await this.$wire.call(this.callbackMethod, this.base64Data);
                    // If no error thrown, the Livewire method handles redirect/fill
                    this.closeModal();
                } catch (err) {
                    this.isAnalyzing = false;
                    this.errorMessage = err.message || "Une erreur est survenue lors de l'analyse.";
                }
            },

            closeModal() {
                if (this.isAnalyzing) return;
                this.stopCamera();
                this.isOpen = false;
                this.resetState();
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
