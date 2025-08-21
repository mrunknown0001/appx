<?php

namespace App\Filament\Resources\InventoryBatchResource\Pages;

use App\Filament\Resources\InventoryBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateInventoryBatch extends CreateRecord
{
    protected static string $resource = InventoryBatchResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Inventory Batch Created')
            ->body('The inventory batch has been created successfully.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate batch number if not provided
        if (empty($data['batch_number'])) {
            $data['batch_number'] = 'BATCH-' . now()->format('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        // Set current_quantity to initial_quantity if not set
        if (empty($data['current_quantity']) && !empty($data['initial_quantity'])) {
            $data['current_quantity'] = $data['initial_quantity'];
        }

        // Auto-set status based on expiry date and quantity
        if ($data['expiry_date'] < now()) {
            $data['status'] = 'expired';
        } elseif ($data['current_quantity'] <= 0) {
            $data['status'] = 'depleted';
        } else {
            $data['status'] = 'active';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $batch = $this->record;
        
        // Check if this creates a low stock situation
        $product = $batch->product;
        if ($product && $batch->current_quantity <= $product->min_stock_level) {
            Notification::make()
                ->warning()
                ->title('Low Stock Alert')
                ->body("The current quantity ({$batch->current_quantity}) is at or below the minimum stock level ({$product->min_stock_level}) for {$product->name}.")
                ->send();
        }

        // Check if expiry date is soon
        $daysUntilExpiry = $batch->expiry_date->diffInDays(now(), false);
        if ($daysUntilExpiry <= 30 && $daysUntilExpiry > 0) {
            Notification::make()
                ->warning()
                ->title('Expiry Warning')
                ->body("This batch expires in {$daysUntilExpiry} days. Consider prioritizing its sale.")
                ->send();
        }
    }
}