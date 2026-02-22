<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Abonnements';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations générales')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Ordre')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Forms\Components\Section::make('Tarification')
                    ->schema([
                        Forms\Components\TextInput::make('price_monthly')
                            ->label('Prix mensuel (centimes)')
                            ->numeric()
                            ->default(0)
                            ->suffix('centimes')
                            ->helperText('Ex: 500 = 5,00 €'),

                        Forms\Components\TextInput::make('price_yearly')
                            ->label('Prix annuel (centimes)')
                            ->numeric()
                            ->default(0)
                            ->suffix('centimes')
                            ->helperText('Ex: 5000 = 50,00 €'),
                    ])->columns(2),

                Forms\Components\Section::make('Paddle')
                    ->schema([
                        Forms\Components\TextInput::make('paddle_monthly_price_id')
                            ->label('Paddle Monthly Price ID')
                            ->helperText('ID du prix mensuel dans Paddle (pri_...)')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('paddle_yearly_price_id')
                            ->label('Paddle Yearly Price ID')
                            ->helperText('ID du prix annuel dans Paddle (pri_...)')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Limites')
                    ->schema([
                        Forms\Components\KeyValue::make('limits')
                            ->label('Limites de fonctionnalités')
                            ->keyLabel('Clé')
                            ->valueLabel('Valeur')
                            ->default([
                                'max_organizations' => 1,
                                'max_users' => 3,
                                'max_assets' => 25,
                                'max_locations' => 1,
                                'max_active_inventory_sessions' => 1,
                                'max_tasks_per_session' => 2,
                                'max_ai_requests_daily' => 0,
                                'max_ai_requests_monthly' => 0,
                                'has_api_access' => false,
                                'has_custom_integrations' => false,
                                'has_advanced_analytics' => false,
                                'has_priority_support' => false,
                                'has_export' => false,
                            ])
                            ->helperText('-1 = illimité, 0 = désactivé, true/false pour les fonctionnalités booléennes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),

                Tables\Columns\TextColumn::make('formatted_monthly_price')
                    ->label('Prix mensuel'),

                Tables\Columns\TextColumn::make('formatted_yearly_price')
                    ->label('Prix annuel'),

                Tables\Columns\TextColumn::make('organizations_count')
                    ->counts('organizations')
                    ->label('Organisations'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Actif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
