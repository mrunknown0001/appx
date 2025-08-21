<?php

namespace App\Filament\Resources\StockEntryResource\Pages;

use App\Filament\Resources\StockEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\InventoryBatch;

class CreateStockEntry extends CreateRecord
{
    protected static string $resource = StockEntryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Stock Entry Created')
            ->body('The stock entry has been created successfully and inventory batch has been generated.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure total_cost is calculated correctly
        if (isset($data['quantity_received']) && isset($data['unit_cost'])) {
            $data['total_cost'] = $data['quantity_received'] * $data['unit_cost'];
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Create corresponding inventory batch after stock entry is created
        $stockEntry = $this->record;
        
        InventoryBatch::create([
            'product_id' => $stockEntry->product_id,
            'stock_entry_id' => $stockEntry->id,
            'batch_number' => $stockEntry->batch_number ?? 'BATCH-' . $stockEntry->id,
            'initial_quantity' => $stockEntry->quantity_received,
            'current_quantity' => $stockEntry->quantity_received,
            'expiry_date' => $stockEntry->expiry_date,
            'location' => 'Main Storage', // Default location
            'status' => 'active',
        ]);

        Notification::make()
            ->success()
            ->title('Inventory Updated')
            ->body('Inventory batch has been automatically created from this stock entry.')
            ->send();
    }
}