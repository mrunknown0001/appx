<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\StockAuditEntry;

class StockAudit extends Model
{
    protected $fillable = [
        'requested_by',
        'date_requested',
        'target_audit_date',
        'actual_audit_date',
        'audited_by',
        'date_audited',
        'remarks',
        'status',
        'completed_at'
    ];


    protected static function booted()
    {
        static::creating(function ($stockAudit) {
            if (empty($stockAudit->date_requested)) {
                $stockAudit->date_requested = now();
            }
            $stockAudit->requested_by = auth()->id();
        });
    }

    public function entries()
    {
        return $this->hasMany(StockAuditEntry::class);
    }
}
