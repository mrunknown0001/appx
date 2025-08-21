<?php

namespace App\Filament\Resources\UnitResource\Pages;

use App\Filament\Resources\UnitResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateUnit extends CreateRecord
{
    protected static string $resource = UnitResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Unit created')
            ->body('The unit has been created successfully.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert abbreviation to lowercase for consistency
        $data['abbreviation'] = strtolower($data['abbreviation']);
        
        return $data;
    }
}