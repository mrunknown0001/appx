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
        'notes'
    ];

    public function stockAudit()
    {
        return $this->belongsTo(StockAudit::class);
    }
}
