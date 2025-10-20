<?php

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InventoryAlertService
{
    public function getOutOfStockProducts(): Collection
    {
        return Product::with('category')
            ->select('products.*')
            ->selectRaw('
                (SELECT COALESCE(SUM(current_quantity), 0)
                 FROM inventory_batches
                 WHERE inventory_batches.product_id = products.id
                 AND inventory_batches.status = "active") as current_stock
            ')
            ->havingRaw('current_stock <= 0 OR current_stock IS NULL')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'current_stock' => (int) ($product->current_stock ?? 0),
                    'min_stock_level' => (int) ($product->min_stock_level ?? 0),
                    'category' => optional($product->category)->name,
                ];
            });
    }

    public function getLowStockProducts(): Collection
    {
        return Product::with('category')
            ->select('products.*')
            ->selectRaw('
                (SELECT COALESCE(SUM(current_quantity), 0)
                 FROM inventory_batches
                 WHERE inventory_batches.product_id = products.id
                 AND inventory_batches.status = "active"
                 AND inventory_batches.expiry_date > NOW()) as current_stock
            ')
            ->havingRaw('current_stock > 0 AND current_stock <= min_stock_level')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'current_stock' => (int) ($product->current_stock ?? 0),
                    'min_stock_level' => (int) ($product->min_stock_level ?? 0),
                    'category' => optional($product->category)->name,
                ];
            });
    }

    public function getExpiredBatches(): Collection
    {
        $now = Carbon::now();

        return InventoryBatch::with('product')
            ->where('expiry_date', '<', $now)
            ->where('status', 'active')
            ->orderBy('expiry_date')
            ->get()
            ->map(function (InventoryBatch $batch) use ($now) {
                $expiryDate = optional($batch->expiry_date);

                return [
                    'id' => $batch->id,
                    'product_name' => optional($batch->product)->name,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $expiryDate?->toDateString(),
                    'quantity' => (int) $batch->current_quantity,
                    'days_overdue' => $expiryDate ? $expiryDate->diffInDays($now) : 0,
                ];
            })
            ->filter(fn (array $batch) => $batch['quantity'] > 0)
            ->values();
    }

    public function getNearExpiryBatches(int $days = 30): Collection
    {
        $now = Carbon::now();
        $threshold = $now->copy()->addDays($days);

        return InventoryBatch::with('product')
            ->whereBetween('expiry_date', [$now, $threshold])
            ->where('status', 'active')
            ->where('current_quantity', '>', 0)
            ->orderBy('expiry_date')
            ->get()
            ->map(function (InventoryBatch $batch) use ($now) {
                $expiryDate = optional($batch->expiry_date);

                return [
                    'id' => $batch->id,
                    'product_name' => optional($batch->product)->name,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $expiryDate?->toDateString(),
                    'quantity' => (int) $batch->current_quantity,
                    'days_remaining' => $expiryDate ? $now->diffInDays($expiryDate) : 0,
                ];
            })
            ->values();
    }

    public function getAlertSummary(): array
    {
        $outOfStock = $this->getOutOfStockProducts();
        $lowStock = $this->getLowStockProducts();
        $expired = $this->getExpiredBatches();
        $nearExpiry = $this->getNearExpiryBatches();

        return [
            'out_of_stock' => [
                'label' => 'Out of Stock',
                'severity' => 'danger',
                'description' => 'Products that have entirely run out of stock.',
                'items' => $outOfStock,
                'count' => $outOfStock->count(),
            ],
            'low_stock' => [
                'label' => 'Low Stock',
                'severity' => 'warning',
                'description' => 'Products that are at or below their minimum stock level.',
                'items' => $lowStock,
                'count' => $lowStock->count(),
            ],
            'expired' => [
                'label' => 'Expired',
                'severity' => 'danger',
                'description' => 'Inventory batches that are already past their expiry date.',
                'items' => $expired,
                'count' => $expired->count(),
            ],
            'near_expiry' => [
                'label' => 'Near Expiry',
                'severity' => 'warning',
                'description' => 'Inventory batches that will expire within the next 30 days.',
                'items' => $nearExpiry,
                'count' => $nearExpiry->count(),
            ],
        ];
    }

    public function getTotalAlertCount(array $summary): int
    {
        return collect($summary)->sum('count');
    }

    public function prepareSummaryForSession(array $summary): array
    {
        return collect($summary)
            ->map(function (array $section) {
                $items = $section['items'] instanceof Collection
                    ? $section['items']->values()->all()
                    : $section['items'];

                return [
                    'label' => $section['label'],
                    'severity' => $section['severity'],
                    'description' => $section['description'],
                    'items' => $items,
                    'count' => $section['count'],
                ];
            })
            ->toArray();
    }
}