<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class StockEntry extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'supplier_name',
        'invoice_number',
        'entry_date',
        'notes',
        'total_quantity',
        'total_cost',
        'items_count',
        'product_id',
        'batch_number',
        'quantity_received',
        'unit_cost',
        'selling_price',
        'expiry_date',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_quantity' => 'integer',
        'items_count' => 'integer',
        'total_cost' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'selling_price' => 'decimal:4',
        'quantity_received' => 'integer',
        'expiry_date' => 'date',
    ];

    public static function booted()
    {
        static::created(function (self $stockEntry) {
            $items = $stockEntry->relationLoaded('items')
                ? $stockEntry->items
                : $stockEntry->items()->get();

            foreach ($items as $item) {
                if (!$item->product_id || !$item->selling_price) {
                    continue;
                }

                $markup = self::getMarkup($item->unit_cost, $item->selling_price);

                $item->product?->priceHistory()->create([
                    'product_id' => $item->product_id,
                    'cost_price' => $item->unit_cost,
                    'selling_price' => $item->selling_price,
                    'markup_percentage' => $markup,
                    'effective_date' => now(),
                ]);
            }
        });
    }

    public static function getMarkup($unit_cost, $selling_price)
    {
        if (!$unit_cost || $unit_cost == 0.0) {
            return 0.0;
        }

        $diff = $selling_price - $unit_cost;
        return ($diff / $unit_cost) * 100;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function items()
    {
        return $this->hasMany(StockEntryItem::class);
    }

    public function inventoryBatches()
    {
        return $this->hasMany(InventoryBatch::class);
    }
}