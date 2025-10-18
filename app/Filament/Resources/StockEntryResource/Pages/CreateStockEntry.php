<?php

namespace App\Filament\Resources\StockEntryResource\Pages;

use App\Filament\Resources\StockEntryResource;
use App\Models\InventoryBatch;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            ->body('The stock entry has been created successfully and inventory batches have been generated.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $items = collect($data['items'] ?? [])
            ->map(function (array $item) {
                $quantity = (int) ($item['quantity_received'] ?? 0);
                $unitCost = (float) ($item['unit_cost'] ?? 0);
                $totalCost = $quantity && $unitCost ? round($quantity * $unitCost, 4) : 0.0;

                return array_merge($item, [
                    'quantity_received' => $quantity,
                    'unit_cost' => $unitCost,
                    'selling_price' => (float) ($item['selling_price'] ?? 0),
                    'total_cost' => $totalCost,
                ]);
            });

        $payloadSummary = [
            'items_count' => $items->count(),
            'total_quantity' => $items->sum('quantity_received'),
            'total_cost' => $items->sum('total_cost'),
            'product_ids' => $items->pluck('product_id')->filter()->values()->all(),
        ];

        Log::info('CreateStockEntry::mutateFormDataBeforeCreate - incoming payload summary', $payloadSummary);

        $data['items'] = $items->map(function (array $item) {
            return Arr::except($item, ['product_label']);
        })->toArray();

        $data['total_quantity'] = $payloadSummary['total_quantity'];
        $data['total_cost'] = $payloadSummary['total_cost'];
        $data['items_count'] = $payloadSummary['items_count'];

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return DB::transaction(function () use ($data) {
            $stockEntry = parent::handleRecordCreation($data);

            $stockEntry->items()->createMany($data['items'] ?? []);

            $stockEntry->refresh();

            return $stockEntry;
        });
    }

    protected function afterCreate(): void
    {
        $stockEntry = $this->record->fresh(['items.product.unit']);

        if (!$stockEntry) {
            Log::warning('CreateStockEntry::afterCreate - record missing after creation');
            return;
        }

        Log::info('CreateStockEntry::afterCreate - stock entry created', [
            'stock_entry_id' => $stockEntry->id,
            'items_count' => $stockEntry->items_count,
            'total_quantity' => $stockEntry->total_quantity,
            'total_cost' => $stockEntry->total_cost,
        ]);

        $batchesCreated = 0;

        /** @var Collection<int,\App\Models\StockEntryItem> $items */
        $items = $stockEntry->items;

        foreach ($items as $item) {
            try {
                $batch = InventoryBatch::create([
                    'product_id' => $item->product_id,
                    'stock_entry_id' => $stockEntry->id,
                    'stock_entry_item_id' => $item->id,
                    'batch_number' => $item->batch_number ?: 'BATCH-' . $stockEntry->id . '-' . $item->id,
                    'initial_quantity' => $item->quantity_received,
                    'current_quantity' => $item->quantity_received,
                    'expiry_date' => $item->expiry_date ?? $stockEntry->entry_date,
                    'location' => 'Main Storage',
                    'status' => 'active',
                ]);

                $batchesCreated++;

                Log::info('CreateStockEntry::afterCreate - inventory batch created', [
                    'stock_entry_id' => $stockEntry->id,
                    'stock_entry_item_id' => $item->id,
                    'inventory_batch_id' => $batch->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity_received,
                ]);
            } catch (Throwable $throwable) {
                Log::error('CreateStockEntry::afterCreate - failed to create inventory batch', [
                    'stock_entry_id' => $stockEntry->id,
                    'stock_entry_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'error' => $throwable->getMessage(),
                ]);

                throw $throwable;
            }
        }

        Notification::make()
            ->success()
            ->title('Inventory Updated')
            ->body("{$batchesCreated} inventory batch(es) have been created.")
            ->send();
    }
}