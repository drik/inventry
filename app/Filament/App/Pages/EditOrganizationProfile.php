<?php

namespace App\Filament\App\Pages;

use App\Models\Organization;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile;
use Illuminate\Support\Str;

class EditOrganizationProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Organization Settings';
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
                    ->unique(Organization::class, 'slug', ignoreRecord: true)
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
}
