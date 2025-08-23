<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'inventory_batch_id',
        'quantity',
        'unit_price',
        'total_price',
        'discount_amount'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'discount_amount' => 'decimal:2'
    ];


    protected static function booted()
    {
        static::created(function ($saleItem) {
            $batch = $saleItem->inventoryBatch;
            if ($batch) {
                $newQuantity = $batch->current_quantity - $saleItem->quantity;
                $batch->update([
                    'current_quantity' => max(0, $newQuantity),
                    'status' => $newQuantity <= 0 ? 'depleted' : 'active'
                ]);
                
                \Log::info('Inventory updated via model event', [
                    'sale_item_id' => $saleItem->id,
                    'batch_id' => $batch->id,
                    'new_quantity' => $batch->current_quantity
                ]);
            }
        });
        
        static::updated(function ($saleItem) {
            if ($saleItem->isDirty('quantity')) {
                $originalQuantity = $saleItem->getOriginal('quantity');
                $quantityDifference = $saleItem->quantity - $originalQuantity;
                
                $batch = $saleItem->inventoryBatch;
                if ($batch && $quantityDifference != 0) {
                    $newQuantity = $batch->current_quantity - $quantityDifference;
                    $batch->update([
                        'current_quantity' => max(0, $newQuantity),
                        'status' => $newQuantity <= 0 ? 'depleted' : 'active'
                    ]);
                }
            }
        });
        
        static::deleted(function ($saleItem) {
            // Return stock when item is deleted
            $batch = $saleItem->inventoryBatch;
            if ($batch) {
                $batch->update([
                    'current_quantity' => $batch->current_quantity + $saleItem->quantity,
                    'status' => 'active'
                ]);
            }
        });
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryBatch()
    {
        return $this->belongsTo(InventoryBatch::class);
    }
}