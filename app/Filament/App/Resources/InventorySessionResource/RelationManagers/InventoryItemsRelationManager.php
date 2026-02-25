<?php

namespace App\Filament\App\Resources\InventorySessionResource\RelationManagers;

use App\Enums\InventoryItemStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InventoryItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->withCount(['notes', 'media'])->with('condition'))
            ->columns([
                Tables\Columns\TextColumn::make('asset.asset_code')
                    ->label('Asset Code')
                    ->searchable(),

                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Asset Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('condition.name')
                    ->label('Condition')
                    ->badge()
                    ->color(fn ($record) => $record->condition?->color ?? 'gray')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('notes_count')
                    ->label('Notes')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'info' : 'gray')
                    ->icon('heroicon-o-pencil-square')
                    ->placeholder('0'),

                Tables\Columns\TextColumn::make('media_count')
                    ->label('Médias')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray')
                    ->icon('heroicon-o-paper-clip')
                    ->placeholder('0'),

                Tables\Columns\TextColumn::make('scanned_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('scanner.name')
                    ->label('Scanned By')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('condition_notes')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(InventoryItemStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('Détails')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn ($record) => ($record->asset?->asset_code ?? '') . ' — ' . ($record->asset?->name ?? 'Item'))
                    ->modalContent(fn ($record) => view('filament.app.resources.inventory-session-resource.partials.item-notes-media', [
                        'item' => $record->load(['asset', 'condition', 'scanner', 'notes.creator', 'notes.sourceMedia', 'media.uploader']),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->slideOver(),

                Tables\Actions\Action::make('mark_found')
                    ->label('Found')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, [
                        InventoryItemStatus::Expected,
                        InventoryItemStatus::Missing,
                    ]))
                    ->action(function ($record): void {
                        $record->update([
                            'status' => InventoryItemStatus::Found,
                            'scanned_at' => now(),
                            'scanned_by' => Auth::id(),
                        ]);
                    }),

                Tables\Actions\Action::make('mark_missing')
                    ->label('Missing')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => in_array($record->status, [
                        InventoryItemStatus::Expected,
                        InventoryItemStatus::Found,
                    ]))
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->update([
                            'status' => InventoryItemStatus::Missing,
                        ]);
                    }),
            ]);
    }
}
