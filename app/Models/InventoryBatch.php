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
        'batch_number',
        'initial_quantity',
        'current_quantity',
        'expiry_date',
        'location',
        'status'
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'initial_quantity' => 'integer',
        'current_quantity' => 'integer'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockEntry()
    {
        return $this->belongsTo(StockEntry::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    // Check if batch is expired
    public function isExpired()
    {
        return $this->expiry_date < now();
    }

    // Check if batch is near expiry (within 30 days)
    public function isNearExpiry($days = 30)
    {
        return $this->expiry_date <= now()->addDays($days);
    }
}