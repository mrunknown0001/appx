<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $table = 'product_stocks';
    public $timestamps = false;
    protected $primaryKey = 'product_id';

    protected $casts = [
        'current_stock' => 'integer',
        'min_stock_level' => 'integer',
        'max_stock_level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function scopeLowStock($query)
    {
        return $query->where('stock_status', 'low_stock');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_status', 'out_of_stock');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_status', 'in_stock');
    }

    public function scopeOverstock($query)
    {
        return $query->where('stock_status', 'overstock');
    }
}