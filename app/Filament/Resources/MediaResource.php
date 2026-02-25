<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages;
use App\Models\Media;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Données';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Médias';

    protected static ?string $modelLabel = 'Média';

    protected static ?string $pluralModelLabel = 'Médias';

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

                Tables\Columns\TextColumn::make('collection')
                    ->label('Collection')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'photos' => 'info',
                        'audio' => 'warning',
                        'video' => 'success',
                        'documents' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'photos' => 'Photo',
                        'audio' => 'Audio',
                        'video' => 'Vidéo',
                        'documents' => 'Document',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('file_name')
                    ->label('Nom du fichier')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Type MIME')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('human_size')
                    ->label('Taille')
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('size_bytes', $direction)),

                Tables\Columns\TextColumn::make('disk')
                    ->label('Disk')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        's3' => 'success',
                        'public' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('mediable_type')
                    ->label('Associé à')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'App\\Models\\InventoryItem' => 'Item inventaire',
                        'App\\Models\\InventoryTask' => 'Tâche inventaire',
                        'App\\Models\\Asset' => 'Actif',
                        default => $state ?? '—',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploadé par')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('collection')
                    ->label('Collection')
                    ->options([
                        'photos' => 'Photos',
                        'audio' => 'Audio',
                        'video' => 'Vidéo',
                        'documents' => 'Documents',
                    ]),

                Tables\Filters\SelectFilter::make('disk')
                    ->options([
                        's3' => 'S3',
                        'public' => 'Public',
                    ]),

                Tables\Filters\SelectFilter::make('mediable_type')
                    ->label('Type associé')
                    ->options([
                        'App\\Models\\InventoryItem' => 'Item inventaire',
                        'App\\Models\\InventoryTask' => 'Tâche inventaire',
                        'App\\Models\\Asset' => 'Actif',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Fichier')
                    ->schema([
                        Infolists\Components\TextEntry::make('file_name')
                            ->label('Nom du fichier'),

                        Infolists\Components\TextEntry::make('collection')
                            ->label('Collection')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'photos' => 'info',
                                'audio' => 'warning',
                                'video' => 'success',
                                'documents' => 'gray',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('mime_type')
                            ->label('Type MIME'),

                        Infolists\Components\TextEntry::make('human_size')
                            ->label('Taille'),

                        Infolists\Components\TextEntry::make('disk')
                            ->label('Stockage')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                's3' => 'success',
                                'public' => 'info',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('file_path')
                            ->label('Chemin')
                            ->columnSpanFull(),
                    ])->columns(2),

                Infolists\Components\Section::make('Aperçu')
                    ->schema([
                        Infolists\Components\ImageEntry::make('file_path')
                            ->label('')
                            ->disk(fn ($record) => ($record->disk === 's3' && ! config('filesystems.disks.s3.bucket')) ? 'public' : $record->disk)
                            ->height(300)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->collection === 'photos'),

                Infolists\Components\Section::make('Association')
                    ->schema([
                        Infolists\Components\TextEntry::make('organization.name')
                            ->label('Organisation'),

                        Infolists\Components\TextEntry::make('mediable_type')
                            ->label('Type associé')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'App\\Models\\InventoryItem' => 'Item inventaire',
                                'App\\Models\\InventoryTask' => 'Tâche inventaire',
                                'App\\Models\\Asset' => 'Actif',
                                default => $state ?? '—',
                            }),

                        Infolists\Components\TextEntry::make('mediable_id')
                            ->label('ID associé'),

                        Infolists\Components\TextEntry::make('uploader.name')
                            ->label('Uploadé par')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Date d\'upload')
                            ->dateTime('d/m/Y H:i:s'),
                    ])->columns(2),

                Infolists\Components\Section::make('Métadonnées')
                    ->schema([
                        Infolists\Components\TextEntry::make('metadata')
                            ->label('')
                            ->state(fn ($record) => $record->metadata ? json_encode($record->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                            ->markdown()
                            ->placeholder('Aucune métadonnée.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => ! empty($record->metadata)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
        ];
    }
}
