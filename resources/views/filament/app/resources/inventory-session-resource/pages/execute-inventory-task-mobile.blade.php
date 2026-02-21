<x-filament-panels::page>
    @push('scripts')
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    @endpush

    {{-- FULLSCREEN MOBILE OVERLAY --}}
    <div
        class="mobile-scanner-overlay"
        x-data="mobileScanner()"
        x-init="init()"
        x-on:mobile-scan-feedback.window="handleFeedback($event.detail)"
        x-on:keydown.escape.window="goBack()"
    >
        {{-- CAMERA: fills entire screen --}}
        <div class="scanner-section">
            <div id="qr-reader-mobile" class="scanner-video-container" wire:ignore></div>

            {{-- Viewfinder (camera mode) --}}
            <div class="viewfinder" x-show="!nfcActive && !manualMode" x-transition.opacity.duration.200ms>
                <div class="viewfinder-corner viewfinder-tl"></div>
                <div class="viewfinder-corner viewfinder-tr"></div>
                <div class="viewfinder-corner viewfinder-bl"></div>
                <div class="viewfinder-corner viewfinder-br"></div>
            </div>

            {{-- NFC mode overlay (replaces camera view) --}}
            <div class="nfc-mode-overlay" x-show="nfcActive" x-transition.opacity.duration.300ms x-cloak>
                <div class="nfc-scan-zone">
                    <div class="nfc-ring nfc-ring-1"></div>
                    <div class="nfc-ring nfc-ring-2"></div>
                    <div class="nfc-ring nfc-ring-3"></div>
                    <div class="nfc-center-icon">
                        <x-fab-nfc-directional class="w-16 h-16" />
                    </div>
                </div>
                <div class="nfc-mode-text">Approchez-vous d'un tag NFC</div>
                <div class="nfc-mode-sub">Placez le dos du t&eacute;l&eacute;phone contre le tag</div>
            </div>

            {{-- Manual input mode overlay (replaces camera view) --}}
            <div class="manual-mode-overlay" x-show="manualMode" x-transition.opacity.duration.300ms x-cloak>
                <div class="manual-scan-zone">
                    <div class="manual-center-icon">
                        <x-bi-keyboard class="w-16 h-16" />
                    </div>
                </div>
                <div class="manual-mode-text">Saisie manuelle</div>
                <div class="manual-mode-sub">Entrez le code barcode ou le code asset ci-dessous</div>

                <div class="manual-input-inline">
                    <input
                        type="text"
                        x-model="manualCode"
                        x-ref="manualInput"
                        x-on:keydown.enter="submitManualCode()"
                        class="manual-input-field"
                        placeholder="Code barcode / asset..."
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                    />
                    <button x-on:click="submitManualCode()" class="manual-search-btn" type="button"
                            :disabled="!manualCode.trim()">
                        <x-heroicon-s-magnifying-glass class="w-5 h-5" />
                    </button>
                </div>
            </div>

            {{-- Scan flash --}}
            <div class="scan-flash" :class="flashClass"></div>

            {{-- Top bar --}}
            <div class="scanner-topbar">
                <a href="{{ route('filament.app.pages.my-inventory-tasks', ['tenant' => \Filament\Facades\Filament::getTenant()]) }}"
                   class="scanner-topbar-btn" x-ref="backLink">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div class="scanner-topbar-title">
                    <div class="scanner-topbar-name">{{ $task->location?->name ?? $this->record->name }}</div>
                    <div class="scanner-topbar-sub">{{ $this->record->name }}</div>
                </div>
                <button
                    x-show="nfcSupported"
                    x-on:click="toggleNfc()"
                    class="nfc-toggle"
                    :class="{ 'active': nfcActive }"
                    type="button"
                    x-cloak
                >
                    <x-ri-rfid-line class="w-5 h-5" />
                </button>
                <div x-show="!nfcSupported" style="width:40px;" x-cloak></div>
            </div>

            {{-- NFC active indicator --}}
            <div x-show="nfcActive" x-cloak class="nfc-indicator">
                <span class="nfc-indicator-dot"></span>
                NFC
            </div>

            {{-- Right-side floating action buttons --}}
            <div class="scanner-right-actions">
                {{-- Flash toggle --}}
                <button
                    x-show="!nfcActive && !manualMode"
                    x-on:click="toggleFlash()"
                    class="scanner-action-btn"
                    :class="{ 'active': flashActive, 'disabled': !flashSupported }"
                    type="button"
                    x-cloak
                >
                    <x-heroicon-s-bolt class="w-5 h-5" />
                </button>

                {{-- Manual input toggle --}}
                <button
                    x-on:click="toggleManualMode()"
                    class="scanner-action-btn"
                    :class="{ 'active': manualMode }"
                    type="button"
                >
                    <x-bi-keyboard-fill class="w-5 h-5" />
                </button>
            </div>

            {{-- Feedback + progress (above bottom sheet) --}}
            <div class="scanner-bottombar" :style="'bottom:' + sheetHeight + 'px'">
                <div x-show="feedbackMessage" x-transition.opacity.duration.200ms
                     class="scanner-feedback"
                     :style="feedbackBgStyle"
                     x-cloak>
                    <span x-text="feedbackMessage"></span>
                    <button x-on:click="dismissFeedback()" class="feedback-dismiss" type="button">&times;</button>
                </div>

                <div x-show="cameraError" x-cloak
                     class="scanner-camera-error"
                     x-text="cameraError">
                </div>

                <div class="scanner-progress">
                    <div class="scanner-progress-bar">
                        <div class="scanner-progress-fill" style="width: {{ $this->stats['progress'] }}%"></div>
                    </div>
                    <span class="scanner-progress-text">
                        {{ $this->stats['scanned'] }}/{{ $this->stats['expected'] }}
                    </span>
                </div>
            </div>
        </div>

        {{-- BOTTOM SHEET --}}
        <div
            class="bottom-sheet"
            :class="{ 'sheet-transitioning': isSnapping }"
            :style="'height:' + sheetHeight + 'px'"
            x-ref="sheet"
        >
            {{-- Drag handle --}}
            <div class="sheet-handle-area"
                 x-on:touchstart.passive="onSheetTouchStart($event)"
                 x-on:touchmove.passive="onSheetTouchMove($event)"
                 x-on:touchend="onSheetTouchEnd($event)"
                 x-on:mousedown="onSheetMouseDown($event)"
            >
                <div class="sheet-handle"></div>
                <div class="sheet-handle-label">
                    <span x-text="sheetExpanded ? 'Glisser pour r\u00e9duire' : 'Glisser pour agrandir'"></span>
                    <span class="sheet-handle-count">{{ $this->stats['expected'] }} assets</span>
                </div>
            </div>

            {{-- Filter tabs --}}
            <div class="asset-list-tabs">
                @foreach(['all' => 'All', 'found' => 'Found', 'expected' => 'Pending', 'missing' => 'Missing', 'unexpected' => 'Extra'] as $tab => $label)
                    <button
                        wire:click="$set('activeTab', '{{ $tab }}')"
                        class="asset-list-tab {{ $activeTab === $tab ? 'active' : '' }}"
                        type="button"
                    >
                        {{ $label }}
                        @if($tab === 'found')
                            <span class="tab-count">{{ $this->stats['found'] }}</span>
                        @elseif($tab === 'expected')
                            <span class="tab-count">{{ $this->stats['expected'] - $this->stats['found'] - $this->stats['missing'] }}</span>
                        @elseif($tab === 'missing')
                            <span class="tab-count">{{ $this->stats['missing'] }}</span>
                        @elseif($tab === 'unexpected')
                            <span class="tab-count">{{ $this->stats['unexpected'] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Scrollable items --}}
            <div class="asset-list-items" x-ref="listItems">
                @forelse($this->items as $item)
                    <div wire:key="mobile-item-{{ $item->id }}" class="asset-list-row {{ $item->status === \App\Enums\InventoryItemStatus::Found ? 'row-found' : '' }}">
                        <div class="asset-thumb">
                            @if($item->asset?->primaryImage)
                                <img src="{{ Storage::disk('public')->url($item->asset->primaryImage->file_path) }}" alt="" />
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#6b7280" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M12 12.75 3 7.5m9 5.25v9M3 7.5v9l9 5.25"/></svg>
                            @endif
                        </div>

                        <div class="asset-info">
                            <div class="asset-name">{{ $item->asset?->name ?? 'Unknown' }}</div>
                            <div class="asset-meta">
                                {{ $item->asset?->category?->name ?? '—' }}
                                &middot;
                                {{ $item->asset?->asset_code ?? '' }}
                            </div>
                        </div>

                        <div class="asset-status-dot">
                            @php
                                $dotColor = match($item->status) {
                                    \App\Enums\InventoryItemStatus::Found => '#22c55e',
                                    \App\Enums\InventoryItemStatus::Missing => '#ef4444',
                                    \App\Enums\InventoryItemStatus::Unexpected => '#eab308',
                                    default => '#6b7280',
                                };
                            @endphp
                            <span style="background: {{ $dotColor }};"></span>
                        </div>
                    </div>
                @empty
                    <div class="asset-list-empty">
                        No items in this filter.
                    </div>
                @endforelse
            </div>

            {{-- Bottom action bar --}}
            <div class="asset-list-actions">
                @if($lastScannedAsset && ($lastScannedAsset['is_unexpected'] ?? false))
                    <button wire:click="addUnexpected('{{ $lastScannedAsset['id'] }}')"
                            class="action-btn action-btn-warning" type="button">
                        Add as Unexpected
                    </button>
                @endif
                @if($task->status !== 'completed')
                    <button wire:click="completeTask" wire:confirm="Mark this task as completed?"
                            class="action-btn action-btn-success" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        Complete Task
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- STYLES --}}
    <style>
        .mobile-scanner-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: #000;
            overflow: hidden;
            touch-action: manipulation;
            -webkit-user-select: none;
            user-select: none;
        }

        /* Camera fills entire viewport */
        .scanner-section {
            position: absolute;
            inset: 0;
            overflow: hidden;
            background: #000;
        }

        .scanner-video-container {
            width: 100%;
            height: 100%;
        }
        .scanner-video-container video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 0 !important;
        }
        #qr-reader-mobile img,
        #qr-reader-mobile > div:first-child > img,
        #qr-reader-mobile #qr-shaded-region {
            display: none !important;
        }

        /* Viewfinder */
        .viewfinder {
            position: absolute;
            top: 38%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 240px;
            height: 240px;
            pointer-events: none;
            z-index: 5;
        }
        .viewfinder-corner {
            position: absolute;
            width: 36px;
            height: 36px;
            border-color: #3b82f6;
            border-style: solid;
        }
        .viewfinder-tl { top: 0; left: 0; border-width: 3px 0 0 3px; border-radius: 6px 0 0 0; }
        .viewfinder-tr { top: 0; right: 0; border-width: 3px 3px 0 0; border-radius: 0 6px 0 0; }
        .viewfinder-bl { bottom: 0; left: 0; border-width: 0 0 3px 3px; border-radius: 0 0 0 6px; }
        .viewfinder-br { bottom: 0; right: 0; border-width: 0 3px 3px 0; border-radius: 0 0 6px 0; }

        /* Flash */
        .scan-flash {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s ease;
            z-index: 8;
        }
        .scan-flash.flash-success { background: rgba(34, 197, 94, 0.35); opacity: 1; }
        .scan-flash.flash-warning { background: rgba(234, 179, 8, 0.35); opacity: 1; }
        .scan-flash.flash-danger { background: rgba(239, 68, 68, 0.35); opacity: 1; }

        /* Top bar */
        .scanner-topbar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            padding: 12px 16px;
            padding-top: calc(12px + env(safe-area-inset-top));
            background: linear-gradient(to bottom, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.3) 70%, transparent 100%);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 10;
        }
        .scanner-topbar-btn {
            color: #fff;
            padding: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .scanner-topbar-title { flex: 1; text-align: center; }
        .scanner-topbar-name { color: #fff; font-size: 15px; font-weight: 600; }
        .scanner-topbar-sub { color: rgba(255,255,255,0.6); font-size: 11px; margin-top: 2px; }

        /* Feedback bar (floats above bottom sheet) */
        .scanner-bottombar {
            position: absolute;
            left: 0;
            right: 0;
            padding: 8px 16px;
            z-index: 10;
            transition: bottom 0.3s ease;
        }
        .scanner-feedback {
            margin-bottom: 6px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .feedback-dismiss {
            background: rgba(255,255,255,0.2);
            border: none;
            color: inherit;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .feedback-dismiss:active { opacity: 0.7; }
        .scanner-camera-error {
            margin-bottom: 6px;
            padding: 8px 12px;
            background: rgba(239,68,68,0.3);
            border-radius: 8px;
            color: #fca5a5;
            font-size: 12px;
            text-align: center;
        }
        .scanner-progress {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .scanner-progress-bar {
            flex: 1;
            height: 5px;
            background: rgba(255,255,255,0.2);
            border-radius: 999px;
            overflow: hidden;
        }
        .scanner-progress-fill {
            height: 100%;
            border-radius: 999px;
            background: #3b82f6;
            transition: width 0.5s ease;
        }
        .scanner-progress-text {
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ===== BOTTOM SHEET ===== */
        .bottom-sheet {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 20;
            background: #ffffff;
            border-radius: 16px 16px 0 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 -4px 24px rgba(0,0,0,0.15);
            will-change: height;
        }
        .bottom-sheet.sheet-transitioning {
            transition: height 0.3s cubic-bezier(0.25, 1, 0.5, 1);
        }

        /* Drag handle area */
        .sheet-handle-area {
            padding: 10px 16px 8px;
            cursor: grab;
            flex-shrink: 0;
            touch-action: none;
        }
        .sheet-handle-area:active { cursor: grabbing; }
        .sheet-handle {
            width: 36px;
            height: 4px;
            border-radius: 999px;
            background: #d1d5db;
            margin: 0 auto 8px;
        }
        .sheet-handle-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #6b7280;
        }
        .sheet-handle-count {
            color: #374151;
            font-weight: 500;
        }

        /* Tabs */
        .asset-list-tabs {
            display: flex;
            gap: 4px;
            padding: 6px 12px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            flex-shrink: 0;
        }
        .asset-list-tabs::-webkit-scrollbar { display: none; }
        .asset-list-tab {
            white-space: nowrap;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            background: #e5e7eb;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s;
        }
        .asset-list-tab.active { background: #3b82f6; color: #fff; }
        .tab-count {
            font-size: 10px;
            background: rgba(0,0,0,0.15);
            padding: 1px 6px;
            border-radius: 999px;
        }

        /* Items list */
        .asset-list-items {
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }

        .asset-list-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
        }
        .asset-list-row.row-found { background: rgba(34, 197, 94, 0.06); }

        .asset-thumb {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            overflow: hidden;
            background: #f3f4f6;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .asset-thumb img { width: 100%; height: 100%; object-fit: cover; }

        .asset-info { flex: 1; min-width: 0; }
        .asset-name {
            font-size: 13px;
            font-weight: 600;
            color: #111827;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .asset-meta {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .asset-status-dot { flex-shrink: 0; }
        .asset-status-dot span { display: block; width: 10px; height: 10px; border-radius: 50%; }

        .asset-list-empty { padding: 32px; text-align: center; color: #9ca3af; font-size: 13px; }

        /* Actions */
        .asset-list-actions {
            padding: 10px 12px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 8px;
            flex-shrink: 0;
            padding-bottom: calc(10px + env(safe-area-inset-bottom));
        }
        .action-btn {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: opacity 0.15s;
        }
        .action-btn:active { opacity: 0.8; }
        .action-btn-success { background: #22c55e; color: #fff; }
        .action-btn-warning { background: #eab308; color: #fff; }

        /* NFC toggle button */
        .nfc-toggle {
            color: #fff;
            padding: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 40px;
            height: 40px;
        }
        .nfc-toggle:active { opacity: 0.7; }
        .nfc-toggle.active {
            background: #3b82f6;
            animation: nfc-btn-pulse 2s ease-in-out infinite;
        }

        /* NFC indicator badge */
        .nfc-indicator {
            position: absolute;
            top: calc(60px + env(safe-area-inset-top));
            right: 16px;
            z-index: 10;
            background: rgba(59, 130, 246, 0.85);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.5px;
        }
        .nfc-indicator-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #86efac;
            animation: nfc-dot-blink 1.5s ease-in-out infinite;
        }

        /* Right-side floating action buttons */
        .scanner-right-actions {
            position: absolute;
            top: calc(90px + env(safe-area-inset-top));
            right: 16px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .scanner-action-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.45);
            color: #fff;
            border: 1.5px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .scanner-action-btn:active { opacity: 0.7; transform: scale(0.92); }
        .scanner-action-btn.active {
            background: #3b82f6;
            border-color: #3b82f6;
            color: #fff;
            box-shadow: 0 0 12px rgba(59, 130, 246, 0.4);
        }
        .scanner-action-btn.disabled {
            opacity: 0.35;
        }

        /* Manual mode overlay */
        .manual-mode-overlay {
            position: absolute;
            inset: 0;
            z-index: 4;
            background: radial-gradient(ellipse at center, #1e293b 0%, #0f172a 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 24px;
            padding: 0 24px;
            padding-bottom: 45%;
        }
        .manual-scan-zone {
            position: relative;
            width: 160px;
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .manual-center-icon {
            position: relative;
            z-index: 2;
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
            width: 88px;
            height: 88px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(59, 130, 246, 0.3);
        }
        .manual-mode-text {
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            margin-top: -16px;
        }
        .manual-mode-sub {
            color: #64748b;
            font-size: 13px;
            margin-top: -20px;
            text-align: center;
        }
        .manual-input-inline {
            display: flex;
            gap: 8px;
            width: 100%;
            max-width: 360px;
            margin-top: 4px;
        }
        .manual-input-field {
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            padding: 12px 16px;
            color: #fff;
            font-size: 16px;
            font-weight: 500;
            outline: none;
        }
        .manual-input-field:focus {
            border-color: #3b82f6;
            background: rgba(255, 255, 255, 0.12);
        }
        .manual-input-field::placeholder {
            color: rgba(255, 255, 255, 0.35);
        }
        .manual-search-btn {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #3b82f6;
            color: #fff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.15s ease;
        }
        .manual-search-btn:active { opacity: 0.8; transform: scale(0.95); }
        .manual-search-btn:disabled {
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.25);
            cursor: default;
        }

        /* NFC mode overlay */
        .nfc-mode-overlay {
            position: absolute;
            inset: 0;
            z-index: 4;
            background: radial-gradient(ellipse at center, #1e293b 0%, #0f172a 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 24px;
            padding-bottom: 45%;
        }
        .nfc-scan-zone {
            position: relative;
            width: 200px;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .nfc-ring {
            position: absolute;
            border-radius: 50%;
            border: 2px solid rgba(59, 130, 246, 0.25);
        }
        .nfc-ring-1 {
            width: 100px; height: 100px;
            animation: nfc-ring-expand 2.5s ease-out infinite;
        }
        .nfc-ring-2 {
            width: 100px; height: 100px;
            animation: nfc-ring-expand 2.5s ease-out 0.8s infinite;
        }
        .nfc-ring-3 {
            width: 100px; height: 100px;
            animation: nfc-ring-expand 2.5s ease-out 1.6s infinite;
        }
        .nfc-center-icon {
            position: relative;
            z-index: 2;
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
            width: 88px;
            height: 88px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(59, 130, 246, 0.3);
        }
        .nfc-mode-text {
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            margin-top: -32px;
        }
        .nfc-mode-sub {
            color: #64748b;
            font-size: 13px;
            margin-top: -20px;
        }
        @keyframes nfc-ring-expand {
            0% { transform: scale(1); opacity: 0.6; border-color: rgba(59, 130, 246, 0.4); }
            100% { transform: scale(2.2); opacity: 0; border-color: rgba(59, 130, 246, 0); }
        }

        @keyframes nfc-btn-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.4); }
            50% { box-shadow: 0 0 0 8px rgba(59,130,246,0); }
        }
        @keyframes nfc-dot-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
    </style>

    {{-- JAVASCRIPT --}}
    <script>
        function mobileScanner() {
            return {
                // Scanner
                scanner: null,
                cameraError: null,
                feedbackMessage: null,
                flashClass: '',
                feedbackBgStyle: '',
                lastScanTime: 0,
                lastScannedCode: '',
                ignoredCodes: new Map(),
                audioCtx: null,
                feedbackTimeout: null,

                // Flash / Torch
                flashSupported: false,
                flashActive: false,

                // Manual input
                manualMode: false,
                manualCode: '',

                // NFC
                nfcSupported: false,
                nfcActive: false,
                nfcReader: null,
                nfcAbortController: null,

                // Bottom sheet
                sheetHeight: 0,
                sheetMinHeight: 0,
                sheetMaxHeight: 0,
                sheetExpanded: false,
                isSnapping: false,
                isDragging: false,
                dragStartY: 0,
                dragStartHeight: 0,

                async init() {
                    // NFC feature detection
                    this.nfcSupported = 'NDEFReader' in window;

                    // Viewport meta
                    const meta = document.querySelector('meta[name="viewport"]');
                    if (meta) {
                        meta.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover');
                    }

                    // Compute sheet snap points
                    this.computeSheetSizes();
                    this.sheetHeight = this.sheetMinHeight;
                    window.addEventListener('resize', () => {
                        this.computeSheetSizes();
                        this.sheetHeight = this.sheetExpanded ? this.sheetMaxHeight : this.sheetMinHeight;
                    });

                    // Mouse move/up for desktop drag
                    window.addEventListener('mousemove', (e) => this.onMouseMove(e));
                    window.addEventListener('mouseup', (e) => this.onMouseUp(e));

                    // Unlock AudioContext on first touch (iOS)
                    document.addEventListener('touchstart', () => {
                        if (!this.audioCtx) {
                            this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                            const buf = this.audioCtx.createBuffer(1, 1, 22050);
                            const src = this.audioCtx.createBufferSource();
                            src.buffer = buf;
                            src.connect(this.audioCtx.destination);
                            src.start(0);
                        }
                    }, { once: true });

                    await this.$nextTick();
                    await this.startScanner();

                    window.addEventListener('beforeunload', () => this.destroy());
                },

                computeSheetSizes() {
                    const vh = window.innerHeight;
                    this.sheetMinHeight = Math.round(vh * 0.35);
                    this.sheetMaxHeight = Math.round(vh * 0.85);
                },

                // --- Touch drag ---
                onSheetTouchStart(e) {
                    this.isDragging = true;
                    this.isSnapping = false;
                    this.dragStartY = e.touches[0].clientY;
                    this.dragStartHeight = this.sheetHeight;
                },
                onSheetTouchMove(e) {
                    if (!this.isDragging) return;
                    const deltaY = this.dragStartY - e.touches[0].clientY;
                    let newHeight = this.dragStartHeight + deltaY;
                    newHeight = Math.max(this.sheetMinHeight, Math.min(this.sheetMaxHeight, newHeight));
                    this.sheetHeight = newHeight;
                },
                onSheetTouchEnd(e) {
                    if (!this.isDragging) return;
                    this.isDragging = false;
                    this.snapSheet();
                },

                // --- Mouse drag (desktop testing) ---
                onSheetMouseDown(e) {
                    e.preventDefault();
                    this.isDragging = true;
                    this.isSnapping = false;
                    this.dragStartY = e.clientY;
                    this.dragStartHeight = this.sheetHeight;
                },
                onMouseMove(e) {
                    if (!this.isDragging) return;
                    const deltaY = this.dragStartY - e.clientY;
                    let newHeight = this.dragStartHeight + deltaY;
                    newHeight = Math.max(this.sheetMinHeight, Math.min(this.sheetMaxHeight, newHeight));
                    this.sheetHeight = newHeight;
                },
                onMouseUp(e) {
                    if (!this.isDragging) return;
                    this.isDragging = false;
                    this.snapSheet();
                },

                snapSheet() {
                    const mid = (this.sheetMinHeight + this.sheetMaxHeight) / 2;
                    this.isSnapping = true;
                    if (this.sheetHeight > mid) {
                        this.sheetHeight = this.sheetMaxHeight;
                        this.sheetExpanded = true;
                    } else {
                        this.sheetHeight = this.sheetMinHeight;
                        this.sheetExpanded = false;
                    }
                    setTimeout(() => { this.isSnapping = false; }, 350);
                },

                // --- Scanner ---
                async startScanner() {
                    this.cameraError = null;
                    if (typeof Html5Qrcode === 'undefined') {
                        this.cameraError = 'Scanner library not loaded. Please refresh the page.';
                        return;
                    }
                    try {
                        this.scanner = new Html5Qrcode('qr-reader-mobile');
                        await this.scanner.start(
                            { facingMode: 'environment' },
                            { fps: 10, qrbox: { width: 240, height: 240 }, disableFlip: false },
                            (decodedText) => this.onDecode(decodedText),
                            () => {}
                        );
                        // Detect torch support after camera is fully ready
                        setTimeout(() => this.detectFlashSupport(), 800);
                    } catch (err) {
                        const errStr = err.toString();
                        if (errStr.includes('NotAllowedError')) {
                            this.cameraError = 'Camera access denied. Please allow camera permissions in your browser settings.';
                        } else if (errStr.includes('NotFoundError')) {
                            this.cameraError = 'No camera found on this device.';
                        } else if (errStr.includes('secure') || errStr.includes('HTTPS')) {
                            this.cameraError = 'Camera requires HTTPS. Please access this page over a secure connection.';
                        } else {
                            this.cameraError = 'Camera error: ' + errStr;
                        }
                    }
                },

                onDecode(decodedText) {
                    const now = Date.now();

                    // Codes ignored for 5s after warning/error
                    const ignoredAt = this.ignoredCodes.get(decodedText);
                    if (ignoredAt && now - ignoredAt < 5000) return;
                    if (ignoredAt) this.ignoredCodes.delete(decodedText);

                    if (decodedText === this.lastScannedCode && now - this.lastScanTime < 5000) return;
                    if (decodedText !== this.lastScannedCode && now - this.lastScanTime < 1000) return;
                    this.lastScannedCode = decodedText;
                    this.lastScanTime = now;
                    this.$wire.set('barcode', decodedText);
                    this.$wire.scanBarcode();
                },

                dismissFeedback() {
                    this.feedbackMessage = null;
                    if (this.feedbackTimeout) {
                        clearTimeout(this.feedbackTimeout);
                        this.feedbackTimeout = null;
                    }
                },

                handleFeedback(detail) {
                    const data = Array.isArray(detail) ? detail[0] : detail;
                    const type = data?.type;
                    const message = data?.message;

                    // Flash
                    this.flashClass = type === 'success' ? 'flash-success' :
                                      type === 'warning' ? 'flash-warning' : 'flash-danger';
                    setTimeout(() => { this.flashClass = ''; }, 350);

                    // Message
                    this.feedbackBgStyle = type === 'success'
                        ? 'background:rgba(34,197,94,0.3); color:#86efac;'
                        : type === 'warning'
                            ? 'background:rgba(234,179,8,0.3); color:#fde047;'
                            : 'background:rgba(239,68,68,0.3); color:#fca5a5;';
                    this.feedbackMessage = message;

                    if (this.feedbackTimeout) clearTimeout(this.feedbackTimeout);

                    if (type === 'success') {
                        // Success: auto-dismiss after 3s
                        this.feedbackTimeout = setTimeout(() => { this.feedbackMessage = null; }, 3000);
                    } else {
                        // Warning/danger: ignore this code for 5s
                        this.ignoredCodes.set(this.lastScannedCode, Date.now());
                    }

                    // Vibration
                    if (navigator.vibrate) {
                        if (type === 'success') navigator.vibrate(100);
                        else if (type === 'warning') navigator.vibrate([50, 30, 50]);
                        else navigator.vibrate([100, 50, 100]);
                    }

                    // Sound
                    this.playBeep(type);
                },

                playBeep(type) {
                    try {
                        if (!this.audioCtx) {
                            this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                        }
                        const ctx = this.audioCtx;
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        if (type === 'success') {
                            osc.frequency.value = 880; gain.gain.value = 0.3;
                            osc.start(); osc.stop(ctx.currentTime + 0.15);
                        } else if (type === 'warning') {
                            osc.frequency.value = 440; gain.gain.value = 0.2;
                            osc.start(); osc.stop(ctx.currentTime + 0.2);
                        } else {
                            osc.frequency.value = 220; gain.gain.value = 0.2;
                            osc.start(); osc.stop(ctx.currentTime + 0.3);
                        }
                    } catch(e) {}
                },

                // --- Flash / Torch ---
                getVideoTrack() {
                    // Try all video elements inside the scanner container
                    const videos = document.querySelectorAll('#qr-reader-mobile video');
                    for (const video of videos) {
                        if (video.srcObject) {
                            const track = video.srcObject.getVideoTracks()[0];
                            if (track && track.readyState === 'live') return track;
                        }
                    }
                    // Fallback: any active video on the page
                    const allVideos = document.querySelectorAll('video');
                    for (const video of allVideos) {
                        if (video.srcObject) {
                            const track = video.srcObject.getVideoTracks()[0];
                            if (track && track.readyState === 'live') return track;
                        }
                    }
                    return null;
                },

                detectFlashSupport() {
                    try {
                        const track = this.getVideoTrack();
                        if (track && track.getCapabilities) {
                            const capabilities = track.getCapabilities();
                            this.flashSupported = !!capabilities.torch;
                        } else {
                            this.flashSupported = false;
                        }
                    } catch(e) {
                        this.flashSupported = false;
                    }
                },

                async toggleFlash() {
                    if (!this.flashSupported) {
                        this.feedbackMessage = 'Flash non disponible sur cet appareil.';
                        this.feedbackBgStyle = 'background:rgba(234,179,8,0.3); color:#fde047;';
                        if (this.feedbackTimeout) clearTimeout(this.feedbackTimeout);
                        this.feedbackTimeout = setTimeout(() => { this.feedbackMessage = null; }, 3000);
                        return;
                    }
                    try {
                        const track = this.getVideoTrack();
                        if (track) {
                            this.flashActive = !this.flashActive;
                            await track.applyConstraints({ advanced: [{ torch: this.flashActive }] });
                        }
                    } catch(e) {
                        this.flashActive = false;
                    }
                },

                // --- Manual input ---
                async toggleManualMode() {
                    if (this.manualMode) {
                        // Exit manual mode: restart camera
                        this.manualMode = false;
                        this.manualCode = '';
                        await this.startScanner();
                    } else {
                        // Enter manual mode: stop camera
                        await this.stopCamera();
                        this.manualMode = true;
                        this.$nextTick(() => {
                            this.$refs.manualInput?.focus();
                        });
                    }
                },

                submitManualCode() {
                    const code = this.manualCode.trim();
                    if (!code) return;
                    this.manualCode = '';
                    this.$wire.set('barcode', code);
                    this.$wire.scanBarcode();
                    // Keep manual mode open for next scan
                    this.$nextTick(() => {
                        this.$refs.manualInput?.focus();
                    });
                },

                // --- NFC ---
                async toggleNfc() {
                    if (this.nfcActive) {
                        // Switch back to camera mode
                        this.stopNfc();
                        await this.startScanner();
                    } else {
                        // Switch to NFC mode: stop camera first
                        await this.stopCamera();
                        await this.startNfc();
                    }
                },

                async stopCamera() {
                    this.flashActive = false;
                    this.flashSupported = false;
                    if (this.scanner) {
                        try { await this.scanner.stop(); } catch(e) {}
                        this.scanner = null;
                    }
                    // Force-release all camera streams
                    const container = document.getElementById('qr-reader-mobile');
                    if (container) {
                        const videos = container.querySelectorAll('video');
                        videos.forEach(v => {
                            if (v.srcObject) {
                                v.srcObject.getTracks().forEach(t => t.stop());
                                v.srcObject = null;
                            }
                        });
                        container.innerHTML = '';
                    }
                },

                async startNfc() {
                    if (!this.nfcSupported) return;

                    try {
                        this.nfcReader = new NDEFReader();
                        this.nfcAbortController = new AbortController();

                        // Bind event listeners BEFORE calling scan()
                        this.nfcReader.addEventListener('reading', (event) => {
                            this.onNfcReading(event);
                        });

                        this.nfcReader.addEventListener('readingerror', () => {
                            // Tag detected but unreadable (likely not NDEF-formatted)
                            this.feedbackMessage = 'Tag NFC d\u00e9tect\u00e9 mais non lisible (format non NDEF). Utilisez des tags NTAG213/215/216.';
                            this.feedbackBgStyle = 'background:rgba(239,68,68,0.3); color:#fca5a5;';
                            this.flashClass = 'flash-danger';
                            setTimeout(() => { this.flashClass = ''; }, 350);
                            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
                            this.playBeep('danger');
                            if (this.feedbackTimeout) clearTimeout(this.feedbackTimeout);
                        });

                        await this.nfcReader.scan({ signal: this.nfcAbortController.signal });

                        this.nfcActive = true;
                        this.cameraError = null;
                    } catch (err) {
                        this.nfcActive = false;
                        const name = err.name || '';
                        if (name === 'NotAllowedError') {
                            this.cameraError = 'Permission NFC refus\u00e9e. Autorisez dans les param\u00e8tres du navigateur.';
                        } else if (name === 'NotSupportedError') {
                            this.cameraError = 'NFC non disponible sur cet appareil.';
                        } else if (name === 'AbortError') {
                            // Scan was aborted (normal stop)
                        } else {
                            this.cameraError = 'Erreur NFC: ' + err.message;
                        }
                        // If NFC failed to start, restart camera
                        if (!this.nfcActive) {
                            await this.startScanner();
                        }
                    }
                },

                stopNfc() {
                    if (this.nfcAbortController) {
                        try { this.nfcAbortController.abort(); } catch(e) {}
                        this.nfcAbortController = null;
                    }
                    this.nfcReader = null;
                    this.nfcActive = false;
                },

                onNfcReading(event) {
                    const code = this.extractNfcCode(event);
                    if (code) {
                        this.onDecode(code);
                    }
                },

                extractNfcCode(event) {
                    const decoder = new TextDecoder();
                    if (event.message && event.message.records) {
                        for (let record of event.message.records) {
                            // NDEF Text record: skip language code prefix (first byte = lang length)
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
                    // Fallback: serial number
                    if (event.serialNumber && event.serialNumber !== '') {
                        return event.serialNumber;
                    }
                    return null;
                },

                goBack() { this.$refs.backLink?.click(); },

                destroy() {
                    if (this.scanner) { try { this.scanner.stop(); } catch(e) {} this.scanner = null; }
                    this.stopNfc();
                    if (this.audioCtx) { try { this.audioCtx.close(); } catch(e) {} this.audioCtx = null; }
                }
            };
        }
    </script>
</x-filament-panels::page>
