<?php

namespace App\Filament\Resources\StockEntryResource\Pages;

use App\Filament\Resources\StockEntryResource;
use App\Models\InventoryBatch;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EditStockEntry extends EditRecord
{
    protected static string $resource = StockEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function () {
                    if ($this->record->inventoryBatches()->exists()) {
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
            ->body('The stock entry has been updated successfully and inventory batches were reconciled.');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $items = $this->record->items()
            ->with('product')
            ->get()
            ->map(function ($item) {
                $product = $item->product;

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_label' => $product ? "{$product->name} ({$product->sku})" : null,
                    'quantity_received' => $item->quantity_received,
                    'unit_cost' => (float) $item->unit_cost,
                    'total_cost' => (float) $item->total_cost,
                    'selling_price' => (float) $item->selling_price,
                    'expiry_date' => $item->expiry_date?->format('Y-m-d'),
                    'batch_number' => $item->batch_number,
                    'notes' => $item->notes,
                ];
            })
            ->toArray();

        $data['items'] = $items;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $items = collect($data['items'] ?? [])
            ->map(function (array $item) {
                $quantity = (int) ($item['quantity_received'] ?? 0);
                $unitCost = round((float) ($item['unit_cost'] ?? 0), 4);
                $sellingPrice = round((float) ($item['selling_price'] ?? 0), 4);
                $totalCost = $quantity > 0 && $unitCost > 0 ? round($quantity * $unitCost, 4) : round((float) ($item['total_cost'] ?? 0), 4);

                return array_merge($item, [
                    'quantity_received' => $quantity,
                    'unit_cost' => $unitCost,
                    'selling_price' => $sellingPrice,
                    'total_cost' => $totalCost,
                ]);
            });

        $data['total_quantity'] = $items->sum('quantity_received');
        $data['total_cost'] = $items->sum('total_cost');
        $data['items_count'] = $items->count();

        $data['items'] = $items
            ->map(fn (array $item) => Arr::except($item, ['product_label']))
            ->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            /** @var Collection<int, array> $itemsPayload */
            $itemsPayload = collect($data['items'] ?? []);

            $summary = [
                'stock_entry_id' => $record->id,
                'items_count' => $itemsPayload->count(),
                'total_quantity' => $itemsPayload->sum('quantity_received'),
                'total_cost' => $itemsPayload->sum('total_cost'),
                'product_ids' => $itemsPayload->pluck('product_id')->filter()->values()->all(),
            ];

            Log::info('EditStockEntry::handleRecordUpdate - incoming payload summary', $summary);

            $record->update(Arr::except($data, ['items']));

            $existingItems = $record->items()->with('inventoryBatch')->get()->keyBy('id');

            $incomingIds = $itemsPayload->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $deleteIds = $existingItems->keys()->diff($incomingIds);

            if ($deleteIds->isNotEmpty()) {
                $existingItems
                    ->only($deleteIds->all())
                    ->each(function ($item) {
                        if ($item->inventoryBatch) {
                            $item->inventoryBatch->delete();
                        }

                        $item->delete();
                    });
            }

            $batchesReconciled = 0;

            foreach ($itemsPayload as $itemData) {
                $itemId = isset($itemData['id']) ? (int) $itemData['id'] : null;
                $productId = $itemData['product_id'] ?? null;

                if (!$productId) {
                    continue;
                }

                $payload = Arr::only($itemData, [
                    'product_id',
                    'quantity_received',
                    'unit_cost',
                    'total_cost',
                    'selling_price',
                    'expiry_date',
                    'batch_number',
                    'notes',
                ]);

                if ($itemId && $existingItems->has($itemId)) {
                    $stockEntryItem = $existingItems->get($itemId);
                    $stockEntryItem->update($payload);
                } else {
                    $stockEntryItem = $record->items()->create($payload);
                }

                $inventoryBatch = $stockEntryItem->inventoryBatch;
                $newInitialQuantity = $payload['quantity_received'];
                $batchPayload = [
                    'product_id' => $productId,
                    'stock_entry_id' => $record->id,
                    'stock_entry_item_id' => $stockEntryItem->id,
                    'batch_number' => $payload['batch_number'] ?: 'BATCH-' . $record->id . '-' . $stockEntryItem->id,
                    'initial_quantity' => $newInitialQuantity,
                    'expiry_date' => $payload['expiry_date'] ?? $record->entry_date,
                    'location' => $inventoryBatch->location ?? 'Main Storage',
                    'status' => $inventoryBatch->status ?? 'active',
                ];

                if ($inventoryBatch) {
                    $quantityDiff = $newInitialQuantity - (int) $inventoryBatch->initial_quantity;
                    $batchPayload['current_quantity'] = max(0, (float) $inventoryBatch->current_quantity + $quantityDiff);
                    $inventoryBatch->update($batchPayload);
                } else {
                    $batchPayload['current_quantity'] = $newInitialQuantity;
                    InventoryBatch::create($batchPayload);
                }

                $batchesReconciled++;
            }

            Log::info('EditStockEntry::handleRecordUpdate - reconciliation complete', [
                'stock_entry_id' => $record->id,
                'items_count' => $itemsPayload->count(),
                'batches_reconciled' => $batchesReconciled,
            ]);

            return $record->fresh(['items.product.unit', 'inventoryBatches']);
        });
    }
}