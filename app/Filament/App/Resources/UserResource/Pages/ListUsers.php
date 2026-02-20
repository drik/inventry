<?php

namespace App\Filament\App\Resources\UserResource\Pages;

use App\Filament\App\Resources\InvitationResource;
use App\Filament\App\Resources\UserResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New User'),

            Actions\Action::make('invitations')
                ->label('Invitations')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->url(InvitationResource::getUrl('index', ['tenant' => Filament::getTenant()])),
        ];
    }
}
