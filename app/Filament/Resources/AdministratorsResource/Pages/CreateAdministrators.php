<?php

namespace App\Filament\Resources\AdministratorsResource\Pages;

use App\Filament\Resources\AdministratorsResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAdministrators extends CreateRecord
{
    protected static string $resource = AdministratorsResource::class;

    public function getTitle(): string
    {
        return 'Create Administrator';
    }
}
