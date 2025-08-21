<?php

namespace App\Filament\Resources\UnitResource\Pages;

use App\Filament\Resources\UnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditUnit extends EditRecord
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function () {
                    if ($this->record->products()->count() > 0) {
                        throw new \Exception('Cannot delete unit that is assigned to products.');
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Unit updated')
            ->body('The unit has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert abbreviation to lowercase for consistency
        $data['abbreviation'] = strtolower($data['abbreviation']);
        
        return $data;
    }
}