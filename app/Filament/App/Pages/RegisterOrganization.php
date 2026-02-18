<?php

namespace App\Filament\App\Pages;

use App\Models\Organization;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;
use Illuminate\Support\Str;

class RegisterOrganization extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'New Organization';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(debounce: 500)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(Organization::class, 'slug')
                    ->rules(['alpha_dash']),

                TextInput::make('phone')
                    ->tel(),

                Textarea::make('address')
                    ->rows(3),

                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->directory('logos')
                    ->maxSize(1024),
            ]);
    }

    protected function handleRegistration(array $data): Organization
    {
        $organization = Organization::create($data);

        $user = auth()->user();
        $organization->update(['owner_id' => $user->id]);
        $user->update(['organization_id' => $organization->id]);

        return $organization;
    }
}
