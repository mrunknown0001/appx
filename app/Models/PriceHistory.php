<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'cost_price',
        'selling_price',
        'markup_percentage',
        'effective_date',
        'reason'
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'markup_percentage' => 'decimal:2',
        'effective_date' => 'date'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}