<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Sale Created')
            ->body('The sale has been created successfully. You can now add items to this sale.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default values for calculated fields to avoid database constraint violations
        $data['subtotal'] = 0.00;
        $data['total_amount'] = 0.00;
        
        // Ensure tax_amount and discount_amount have default values
        $data['tax_amount'] = $data['tax_amount'] ?? 0.00;
        $data['discount_amount'] = $data['discount_amount'] ?? 0.00;
        
        // Generate sale number if not provided
        if (empty($data['sale_number'])) {
            $data['sale_number'] = 'SALE-' . strtoupper(uniqid());
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Additional notification about adding items
        Notification::make()
            ->info()
            ->title('Next Step')
            ->body('Add sale items using the "Sale Items" tab to complete this sale.')
            ->persistent()
            ->send();
    }
}