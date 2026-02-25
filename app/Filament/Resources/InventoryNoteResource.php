<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryNoteResource\Pages;
use App\Models\InventoryNote;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryNoteResource extends Resource
{
    protected static ?string $model = InventoryNote::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'Notes d\'inventaire';

    protected static ?string $modelLabel = 'Note d\'inventaire';

    protected static ?string $pluralModelLabel = 'Notes d\'inventaire';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->searchable()
                    ->sortable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'text' => 'gray',
                        'ai_rephrase' => 'info',
                        'ai_photo_desc' => 'primary',
                        'ai_audio_transcript' => 'warning',
                        'ai_video_desc' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'text' => 'Texte',
                        'ai_rephrase' => 'IA - Reformulation',
                        'ai_photo_desc' => 'IA - Description photo',
                        'ai_audio_transcript' => 'IA - Transcription audio',
                        'ai_video_desc' => 'IA - Description vidéo',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('content')
                    ->label('Contenu')
                    ->limit(60)
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('notable_type')
                    ->label('Associé à')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'App\\Models\\InventoryItem' => 'Item',
                        'App\\Models\\InventoryTask' => 'Tâche',
                        default => $state ?? '—',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'App\\Models\\InventoryItem' => 'info',
                        'App\\Models\\InventoryTask' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Créé par')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('source_media_id')
                    ->label('Média')
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->state(fn ($record) => $record->source_media_id !== null),

                Tables\Columns\IconColumn::make('ai_usage_log_id')
                    ->label('Log IA')
                    ->boolean()
                    ->trueIcon('heroicon-o-sparkles')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->state(fn ($record) => $record->ai_usage_log_id !== null),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('source_type')
                    ->label('Source')
                    ->options([
                        'text' => 'Texte manuel',
                        'ai_rephrase' => 'IA - Reformulation',
                        'ai_photo_desc' => 'IA - Description photo',
                        'ai_audio_transcript' => 'IA - Transcription audio',
                        'ai_video_desc' => 'IA - Description vidéo',
                    ]),

                Tables\Filters\SelectFilter::make('notable_type')
                    ->label('Type associé')
                    ->options([
                        'App\\Models\\InventoryItem' => 'Item inventaire',
                        'App\\Models\\InventoryTask' => 'Tâche inventaire',
                    ]),

                Tables\Filters\TernaryFilter::make('has_ai')
                    ->label('Généré par IA')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('ai_usage_log_id'),
                        false: fn ($query) => $query->whereNull('ai_usage_log_id'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Note')
                    ->schema([
                        Infolists\Components\TextEntry::make('source_type')
                            ->label('Source')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'text' => 'gray',
                                'ai_rephrase' => 'info',
                                'ai_photo_desc' => 'primary',
                                'ai_audio_transcript' => 'warning',
                                'ai_video_desc' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'text' => 'Texte manuel',
                                'ai_rephrase' => 'IA - Reformulation',
                                'ai_photo_desc' => 'IA - Description photo',
                                'ai_audio_transcript' => 'IA - Transcription audio',
                                'ai_video_desc' => 'IA - Description vidéo',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Date')
                            ->dateTime('d/m/Y H:i:s'),

                        Infolists\Components\TextEntry::make('content')
                            ->label('Contenu')
                            ->columnSpanFull(),
                    ])->columns(2),

                Infolists\Components\Section::make('Texte original (avant IA)')
                    ->schema([
                        Infolists\Components\TextEntry::make('original_content')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->original_content !== null)
                    ->collapsible(),

                Infolists\Components\Section::make('Association')
                    ->schema([
                        Infolists\Components\TextEntry::make('organization.name')
                            ->label('Organisation'),

                        Infolists\Components\TextEntry::make('notable_type')
                            ->label('Type associé')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'App\\Models\\InventoryItem' => 'Item inventaire',
                                'App\\Models\\InventoryTask' => 'Tâche inventaire',
                                default => $state ?? '—',
                            }),

                        Infolists\Components\TextEntry::make('notable_id')
                            ->label('ID associé'),

                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Créé par')
                            ->placeholder('—'),
                    ])->columns(2),

                Infolists\Components\Section::make('Média source')
                    ->schema([
                        Infolists\Components\TextEntry::make('sourceMedia.file_name')
                            ->label('Fichier'),

                        Infolists\Components\TextEntry::make('sourceMedia.collection')
                            ->label('Collection')
                            ->badge(),

                        Infolists\Components\TextEntry::make('sourceMedia.human_size')
                            ->label('Taille'),

                        Infolists\Components\TextEntry::make('sourceMedia.mime_type')
                            ->label('Type MIME'),

                        Infolists\Components\ImageEntry::make('sourceMedia.file_path')
                            ->label('Aperçu')
                            ->disk(fn ($record) => ($record->sourceMedia?->disk === 's3' && ! config('filesystems.disks.s3.bucket')) ? 'public' : ($record->sourceMedia?->disk ?? 'public'))
                            ->height(200)
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->sourceMedia?->collection === 'photos'),
                    ])->columns(2)
                    ->visible(fn ($record) => $record->source_media_id !== null)
                    ->collapsible(),

                Infolists\Components\Section::make('Log IA associé')
                    ->schema([
                        Infolists\Components\TextEntry::make('aiUsageLog.feature')
                            ->label('Fonctionnalité')
                            ->badge(),

                        Infolists\Components\TextEntry::make('aiUsageLog.tokens_used')
                            ->label('Tokens utilisés')
                            ->numeric(),

                        Infolists\Components\TextEntry::make('aiUsageLog.created_at')
                            ->label('Date')
                            ->dateTime('d/m/Y H:i:s'),
                    ])->columns(3)
                    ->visible(fn ($record) => $record->ai_usage_log_id !== null)
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryNotes::route('/'),
        ];
    }
}
