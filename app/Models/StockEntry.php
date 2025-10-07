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

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryBatch()
    {
        return $this->hasOne(InventoryBatch::class);
    }
}