<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockEntryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_entry_id',
        'product_id',
        'quantity_received',
        'unit_cost',
        'total_cost',
        'selling_price',
        'expiry_date',
        'batch_number',
        'notes',
    ];

    protected $casts = [
        'quantity_received' => 'integer',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'selling_price' => 'decimal:4',
        'expiry_date' => 'date',
    ];

    public function stockEntry()
    {
        return $this->belongsTo(StockEntry::class);
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