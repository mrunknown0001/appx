<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function () {
                    if ($this->record->stockEntries()->count() > 0) {
                        throw new \Exception('Cannot delete product that has stock entries.');
                    }
                    if ($this->record->saleItems()->count() > 0) {
                        throw new \Exception('Cannot delete product that has sales history.');
                    }
                }),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
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
            ->title('Product updated')
            ->body('The product has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert SKU to uppercase
        $data['sku'] = strtoupper($data['sku']);

        return $data;
    }
}