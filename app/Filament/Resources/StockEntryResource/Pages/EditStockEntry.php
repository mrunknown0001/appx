<?php

namespace App\Filament\Resources\StockEntryResource\Pages;

use App\Filament\Resources\StockEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditStockEntry extends EditRecord
{
    protected static string $resource = StockEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function () {
                    // Check if this stock entry has associated inventory batches
                    if ($this->record->inventoryBatch()->exists()) {
                        throw new \Exception('Cannot delete stock entry that has associated inventory batches.');
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
            ->title('Stock Entry Updated')
            ->body('The stock entry has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure total_cost is calculated correctly
        if (isset($data['quantity_received']) && isset($data['unit_cost'])) {
            $data['total_cost'] = $data['quantity_received'] * $data['unit_cost'];
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Update corresponding inventory batch if it exists
        $stockEntry = $this->record;
        $inventoryBatch = $stockEntry->inventoryBatch;
        
        if ($inventoryBatch) {
            // Only update if quantity or expiry date changed
            $shouldUpdate = false;
            $updates = [];
            
            if ($inventoryBatch->initial_quantity != $stockEntry->quantity_received) {
                $quantityDiff = $stockEntry->quantity_received - $inventoryBatch->initial_quantity;
                $updates['initial_quantity'] = $stockEntry->quantity_received;
                $updates['current_quantity'] = $inventoryBatch->current_quantity + $quantityDiff;
                $shouldUpdate = true;
            }
            
            if ($inventoryBatch->expiry_date != $stockEntry->expiry_date) {
                $updates['expiry_date'] = $stockEntry->expiry_date;
                $shouldUpdate = true;
            }
            
            if ($inventoryBatch->batch_number != $stockEntry->batch_number && $stockEntry->batch_number) {
                $updates['batch_number'] = $stockEntry->batch_number;
                $shouldUpdate = true;
            }
            
            if ($shouldUpdate) {
                $inventoryBatch->update($updates);
                
                Notification::make()
                    ->success()
                    ->title('Inventory Updated')
                    ->body('Related inventory batch has been updated to reflect the changes.')
                    ->send();
            }
        }
    }
}