<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\StockAuditEntry;

class StockAudit extends Model
{
    protected $fillable = [
        'requested_by',
        'date_requested',
        'audited_by',
        'date_audited',
        'remarks',
        'status'
    ];


    public function stockAuditItems()
    {
        return $this->hasMany(StockAuditEntry::class);
    }
}
