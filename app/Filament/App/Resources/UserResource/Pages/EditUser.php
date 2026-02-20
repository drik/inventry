<?php

namespace App\Filament\App\Resources\UserResource\Pages;

use App\Filament\App\Resources\UserResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->getRecord()->id === auth()->id()
                    || $this->getRecord()->id === Filament::getTenant()->owner_id),
            Actions\RestoreAction::make(),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        $record = $this->getRecord();
        $tenant = Filament::getTenant();

        abort_unless($record->organization_id === $tenant->id, 403);
    }
}
