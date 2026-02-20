<?php

namespace App\Filament\App\Resources;

use App\Enums\UserRole;
use App\Filament\App\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRoleAtLeast(UserRole::Admin);
    }

    public static function form(Form $form): Form
    {
        $currentUser = auth()->user();
        $assignableRoles = UserRole::assignableBy($currentUser->role);

        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: User::class,
                                column: 'email',
                                ignorable: fn (?User $record) => $record,
                                modifyRuleUsing: fn ($rule) => $rule->where('organization_id', Filament::getTenant()->id),
                            ),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->visibleOn('create'),

                        Forms\Components\Select::make('role')
                            ->options(
                                collect($assignableRoles)
                                    ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->getLabel()])
                                    ->all()
                            )
                            ->required()
                            ->default(UserRole::User->value)
                            ->disabled(fn (?User $record) => $record && $record->id === auth()->id()),

                        Forms\Components\Select::make('department_id')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->nullable(),
                    ])->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive users cannot log in.')
                            ->disabled(fn (?User $record) => $record && (
                                $record->id === auth()->id()
                                || $record->id === Filament::getTenant()->owner_id
                            )),
                    ])
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(UserRole::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Department')
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('toggleActive')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['is_active' => ! $record->is_active]))
                    ->hidden(fn ($record) => $record->id === auth()->id()
                        || $record->id === Filament::getTenant()->owner_id),

                Tables\Actions\DeleteAction::make()
                    ->hidden(fn ($record) => $record->id === auth()->id()
                        || $record->id === Filament::getTenant()->owner_id),

                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organization_id', Filament::getTenant()->id)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
