<?php

namespace App\Filament\Resources\StockEntryResource\Pages;

use App\Filament\Resources\StockEntryResource;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
        Log::info('CreateStockEntry::mutateFormDataBeforeCreate - raw data snapshot', [
            'data_keys' => is_array($data) ? array_keys($data) : [],
            'items_present' => array_key_exists('items', $data),
            'items_type' => gettype($data['items'] ?? null),
            'items_count' => is_countable($data['items'] ?? null) ? count($data['items']) : null,
        ]);

        $supplierName = $data['supplier_name'] ?? null;

        if ($supplierName instanceof Supplier) {
            $supplierName = $supplierName->name;
        } elseif (is_array($supplierName)) {
            $first = Arr::first($supplierName);
            $supplierName = Arr::get($supplierName, 'name')
                ?? (is_array($first) ? Arr::get($first, 'name') : $first);
        } elseif (is_string($supplierName)) {
            $decoded = json_decode($supplierName, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $supplierName = Arr::get($decoded, 'name', $supplierName);
            }
        }

        $data['supplier_name'] = $supplierName;

        Log::info('CreateStockEntry::mutateFormDataBeforeCreate - livewire data snapshot', [
            'data_property_keys' => array_keys($this->data ?? []),
            'data_property_items_present' => is_array($this->data ?? null) && array_key_exists('items', $this->data),
            'data_property_items_type' => gettype(($this->data ?? [])['items'] ?? null),
            'data_property_items_count' => is_countable(($this->data ?? [])['items'] ?? null) ? count($this->data['items']) : null,
        ]);

        $formState = $this->form->getState();
        $itemsComponent = $this->form->getComponent('items');
        $itemsComponentState = $itemsComponent ? $itemsComponent->getState() : null;

        Log::info('CreateStockEntry::mutateFormDataBeforeCreate - items component snapshot', [
            'component_exists' => (bool) $itemsComponent,
            'component_state_type' => gettype($itemsComponentState),
            'component_state_count' => is_countable($itemsComponentState) ? count($itemsComponentState) : null,
            'component_first_item' => is_array($itemsComponentState) && $itemsComponentState ? $itemsComponentState[0] : null,
        ]);

        $livewireData = $this->data ?? [];
        $itemsState = $data['items']
            ?? ($livewireData['items'] ?? null)
            ?? ($formState['items'] ?? null)
            ?? $itemsComponentState
            ?? [];

        if ($itemsState instanceof \Illuminate\Support\Collection) {
            $itemsState = $itemsState->toArray();
        }

        if (! is_array($itemsState)) {
            $itemsState = [];
        }

        Log::info('CreateStockEntry::mutateFormDataBeforeCreate - items state snapshot', [
            'items_state_type' => gettype($itemsState),
            'items_state_count' => is_countable($itemsState) ? count($itemsState) : null,
            'first_item_keys' => is_array($itemsState) && $itemsState ? array_keys(($itemsState[array_key_first($itemsState)] ?? [])) : null,
            'first_item_raw' => is_array($itemsState) && $itemsState ? ($itemsState[array_key_first($itemsState)] ?? null) : null,
        ]);

        $items = collect($itemsState)
            ->filter(fn ($item) => is_array($item))
            ->values()
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

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Please add at least one product to this stock entry.',
            ]);
        }

        $payloadSummary = [
            'items_count' => $items->count(),
            'total_quantity' => $items->sum('quantity_received'),
            'total_cost' => $items->sum('total_cost'),
            'product_ids' => $items->pluck('product_id')->filter()->values()->all(),
        ];

        Log::info('CreateStockEntry::mutateFormDataBeforeCreate - incoming payload summary', $payloadSummary);

        $resolvedProductId = $data['product_id'] ?? collect($payloadSummary['product_ids'])
            ->map(fn ($id) => (int) $id)
            ->first();

        if (! $resolvedProductId) {
            throw ValidationException::withMessages([
                'items' => 'A product must be selected for each item in the stock entry.',
            ]);
        }

        $data['items'] = $items->map(function (array $item) {
            return Arr::except($item, ['product_label']);
        })->toArray();

        $firstItem = $items->first();

        $data['product_id'] = (int) $resolvedProductId;
        $data['total_quantity'] = $payloadSummary['total_quantity'];
        $data['total_cost'] = $payloadSummary['total_cost'];
        $data['items_count'] = $payloadSummary['items_count'];

        $legacyColumnFallbacks = [
            'quantity_received' => (int) ($firstItem['quantity_received'] ?? $payloadSummary['total_quantity']),
            'unit_cost' => (float) ($firstItem['unit_cost'] ?? 0),
            'selling_price' => array_key_exists('selling_price', $firstItem ?? []) ? (float) $firstItem['selling_price'] : null,
            'expiry_date' => $firstItem['expiry_date'] ?? ($data['entry_date'] ?? null),
            'batch_number' => $firstItem['batch_number'] ?? null,
        ];

        if ($legacyColumnFallbacks['expiry_date'] === null) {
            $legacyColumnFallbacks['expiry_date'] = $data['entry_date'] ?? now();
        }

        $data = array_merge($data, $legacyColumnFallbacks);

        Log::info('CreateStockEntry::mutateFormDataBeforeCreate - normalized payload', [
            'supplier_name' => $data['supplier_name'],
            'product_id' => $data['product_id'],
            'items_count' => $data['items_count'],
            'total_quantity' => $data['total_quantity'],
            'total_cost' => $data['total_cost'],
        ]);

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
            'product_ids' => $stockEntry->items->pluck('product_id')->unique()->values()->all(),
        ]);

        $batchesCreated = 0;

        /** @var Collection<int,\App\Models\StockEntryItem> $items */
        $items = $stockEntry->items;

        foreach ($items as $item) {
            $product = $item->product;
            $productStockBefore = $product ? $product->getCurrentStock() : null;

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
                    'product_stock_before' => $productStockBefore,
                    'product_stock_after' => $product ? optional($product->fresh())->getCurrentStock() : null,
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