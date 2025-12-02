<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAuditEntry extends Model
{
    protected $fillable = [
        'stock_audit_id',
        'product_id',
        'expected_quantity',
        'actual_quantity',
        'difference',
        'is_audited',
        'matched',
        'remarks'
    ];

    public function stockAudit()
    {
        return $this->belongsTo(StockAudit::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
