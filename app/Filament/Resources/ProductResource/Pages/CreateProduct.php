<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Product created')
            ->body('The product has been created successfully.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate SKU if not provided
        if (empty($data['sku'])) {
            $data['sku'] = 'PRD-' . strtoupper(uniqid());
        }

        // Convert SKU to uppercase
        $data['sku'] = strtoupper($data['sku']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // You can add logic here to create initial price history entry
        // or perform other post-creation tasks
    }
}