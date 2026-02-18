<?php

namespace App\Filament\App\Resources\AssetResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file_path')
                    ->label('Image')
                    ->image()
                    ->directory('asset-images')
                    ->required(),

                Forms\Components\TextInput::make('caption')
                    ->maxLength(255),

                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary image'),

                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('file_path')
                    ->label('Image')
                    ->circular(),

                Tables\Columns\TextColumn::make('caption')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean()
                    ->label('Primary'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
