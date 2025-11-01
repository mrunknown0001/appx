<?php

namespace App\Filament\Resources\AdministratorsResource\Pages;

use App\Filament\Resources\AdministratorsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdministrators extends ListRecords
{
    protected static string $resource = AdministratorsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Administrator'),
        ];
    }
}
