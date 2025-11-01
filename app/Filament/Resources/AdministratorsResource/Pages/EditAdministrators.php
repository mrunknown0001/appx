<?php

namespace App\Filament\Resources\AdministratorsResource\Pages;

use App\Filament\Resources\AdministratorsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdministrators extends EditRecord
{
    protected static string $resource = AdministratorsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Edit Administrator';
    }
}
