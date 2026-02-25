<div class="space-y-6">
    {{-- Notes Section --}}
    <div>
        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
            <x-heroicon-o-pencil-square class="h-4 w-4 text-gray-500" />
            Notes ({{ $taskNotes->count() }})
        </h3>

        @if($taskNotes->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucune note pour cette tâche.</p>
        @else
            <div class="space-y-3">
                @foreach($taskNotes as $note)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <div class="mb-2 flex items-center gap-2">
                            @php
                                $sourceColor = match($note->source_type) {
                                    'text' => 'gray',
                                    'ai_rephrase' => 'success',
                                    'ai_photo_desc' => 'primary',
                                    'ai_audio_transcript' => 'warning',
                                    'ai_video_desc' => 'info',
                                    default => 'gray',
                                };
                                $sourceLabel = match($note->source_type) {
                                    'text' => 'Texte',
                                    'ai_rephrase' => 'IA - Reformulation',
                                    'ai_photo_desc' => 'IA - Photo',
                                    'ai_audio_transcript' => 'IA - Audio',
                                    'ai_video_desc' => 'IA - Vidéo',
                                    default => $note->source_type,
                                };
                            @endphp
                            <x-filament::badge :color="$sourceColor" size="sm">
                                {{ $sourceLabel }}
                            </x-filament::badge>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $note->creator?->name ?? '—' }} · {{ $note->created_at->diffForHumans() }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $note->content }}</p>
                        @if($note->original_content)
                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                    Texte original
                                </summary>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 italic">{{ $note->original_content }}</p>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Media Section --}}
    <div>
        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
            <x-heroicon-o-paper-clip class="h-4 w-4 text-gray-500" />
            Médias ({{ $taskMediaItems->count() }})
        </h3>

        @if($taskMediaItems->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucun média pour cette tâche.</p>
        @else
            {{-- Photos grid --}}
            @php $photos = $taskMediaItems->where('collection', 'photos'); @endphp
            @if($photos->isNotEmpty())
                <div class="mb-4">
                    <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Photos ({{ $photos->count() }})</p>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($photos as $photo)
                            <a href="{{ $photo->url }}" target="_blank" class="group relative aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                                <img src="{{ $photo->url }}" alt="{{ $photo->file_name }}" class="h-full w-full object-cover transition group-hover:scale-105" />
                                <div class="absolute inset-0 bg-black/0 transition group-hover:bg-black/20"></div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Audio/Video list --}}
            @php $otherMedia = $taskMediaItems->whereIn('collection', ['audio', 'video']); @endphp
            @if($otherMedia->isNotEmpty())
                <div class="space-y-2">
                    @foreach($otherMedia as $media)
                        <a href="{{ $media->url }}" target="_blank"
                           class="flex items-center gap-3 rounded-lg border border-gray-200 p-2 transition hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5">
                            @if($media->collection === 'audio')
                                <x-heroicon-o-musical-note class="h-5 w-5 shrink-0 text-amber-500" />
                            @else
                                <x-heroicon-o-video-camera class="h-5 w-5 shrink-0 text-blue-500" />
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-gray-700 dark:text-gray-300">{{ $media->file_name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $media->humanSize }} · {{ $media->uploader?->name ?? '—' }} · {{ $media->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 shrink-0 text-gray-400" />
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
