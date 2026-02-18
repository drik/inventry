<?php

namespace App\Filament\App\Resources\AssetCategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CustomFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'customFields';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->live(debounce: 500)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('field_key', Str::slug($state, '_'))),

                Forms\Components\TextInput::make('field_key')
                    ->required()
                    ->rules(['alpha_dash']),

                Forms\Components\Select::make('field_type')
                    ->required()
                    ->options([
                        'text' => 'Text',
                        'number' => 'Number',
                        'date' => 'Date',
                        'select' => 'Select',
                        'boolean' => 'Boolean',
                        'url' => 'URL',
                    ])
                    ->reactive(),

                Forms\Components\TagsInput::make('options')
                    ->visible(fn (Forms\Get $get) => $get('field_type') === 'select'),

                Forms\Components\Toggle::make('is_required'),

                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable(),

                Tables\Columns\TextColumn::make('field_key'),

                Tables\Columns\TextColumn::make('field_type')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_required')
                    ->boolean(),

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
