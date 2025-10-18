<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'stock_entry_id',
        'stock_entry_item_id',
        'batch_number',
        'initial_quantity',
        'current_quantity',
        'expiry_date',
        'location',
        'status',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'initial_quantity' => 'decimal:4',
        'current_quantity' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockEntry()
    {
        return $this->belongsTo(StockEntry::class);
    }

    public function stockEntryItem()
    {
        return $this->belongsTo(StockEntryItem::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    // Check if batch is expired
    public function isExpired()
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date < now();
    }

    // Check if batch is near expiry (within 30 days)
    public function isNearExpiry($days = 30)
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date <= now()->addDays($days);
    }
}