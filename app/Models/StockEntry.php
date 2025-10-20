<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class StockEntry extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'supplier_name',
        'invoice_number',
        'entry_date',
        'quantity_received',
        'unit_cost',
        'total_cost',
        'selling_price',
        'expiry_date',
        'batch_number',
        'total_quantity',
        'items_count',
        'notes',
        'product_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_quantity' => 'integer',
        'items_count' => 'integer',
        'total_cost' => 'decimal:4',
    ];

    public static function booted()
    {
        // stock entry created, add record to price history using the selling_price value
        static::created(function (self $stockEntry) {
            if (! $stockEntry->product_id || ! $stockEntry->product) {
                return;
            }

            $unitCost = $stockEntry->unit_cost ?? null;
            $sellingPrice = $stockEntry->selling_price ?? null;

            if ($unitCost === null || $sellingPrice === null) {
                return;
            }

            $stockEntry->product->priceHistory()->create([
                'product_id' => $stockEntry->product_id,
                'cost_price' => $unitCost,
                'selling_price' => $sellingPrice,
                'markup_percentage' => self::getMarkup($unitCost, $sellingPrice),
                'effective_date' => now(),
            ]);
        });
    }

    public static function getMarkup($unit_cost, $selling_price)
    {
        if ($unit_cost == 0.0) {
            return 0.0;
        }

        $diff = $selling_price - $unit_cost;
        return ($diff / $unit_cost) * 100;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockEntryItem::class);
    }

    public function inventoryBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }
}