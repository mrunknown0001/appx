<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class StockEntry extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'product_id',
        'supplier_name',
        'invoice_number',
        'entry_date',
        'quantity_received',
        'unit_cost',
        'total_cost',
        'expiry_date',
        'batch_number',
        'notes',
        'selling_price'
    ];

    protected $casts = [
        'entry_date' => 'date',
        'expiry_date' => 'date',
        'quantity_received' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'selling_price' => 'decimal:2'
    ];

    public static function booted()
    {
        // stock entry created, add record to price history using the selling_price value
        static::created(function ($stockEntry) {
            // add price history using the product_id and selling_price
            $stockEntry->product->priceHistory()->create([
                'product_id' => $stockEntry->product_id,
                'cost_price' => $stockEntry->unit_cost,
                'selling_price' => $stockEntry->selling_price,
                'markup_percentage' => self::getMarkup($stockEntry->unit_cost, $stockEntry->selling_price),
                'effective_date' => now(),
            ]);
        });
    }

    public static function getMarkup($unit_cost, $selling_price)
    {
        $diff = $selling_price - $unit_cost;
        return ($diff/$unit_cost) * 100;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryBatch()
    {
        return $this->hasOne(InventoryBatch::class);
    }
}