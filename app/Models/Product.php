<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Product extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'description',
        'sku',
        'barcode',
        'product_category_id', // Fixed to match migration
        'unit_id',
        'manufacturer',
        'generic_name',
        'strength',
        'dosage_form',
        'min_stock_level',
        'max_stock_level',
        'is_prescription_required',
        'is_active'
    ];

    protected $casts = [
        'is_prescription_required' => 'boolean',
        'is_active' => 'boolean',
        'min_stock_level' => 'integer',
        'max_stock_level' => 'integer'
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id'); // Fixed relationship
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockEntryItems()
    {
        return $this->hasMany(StockEntryItem::class);
    }

    public function stockEntries()
    {
        return $this->hasManyThrough(
            StockEntry::class,
            StockEntryItem::class,
            'product_id',
            'id',
            'id',
            'stock_entry_id'
        )->distinct();
    }

    public function inventoryBatches()
    {
        // return $this->hasMany(InventoryBatch::class);
        return $this->hasMany(InventoryBatch::class)->where('expiry_date', '>', now())->where('status', 'active');
    }

    public function priceHistory()
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    // Computed attributes and methods
    public function getCurrentStock(): int
    {
        return $this->inventoryBatches()
            ->where('expiry_date', '>', now())
            ->where('status', 'active')
            ->sum('current_quantity') ?? 0;
    }

    public function getCurrentPrice(): float
    {
        return $this->priceHistory()
            ->where('effective_date', '<=', now())
            ->latest('effective_date')
            ->first()?->selling_price ?? 0.00;
    }

    public function getStockStatus(): string
    {
        $currentStock = $this->getCurrentStock();
        
        if ($currentStock <= 0) {
            return 'out_of_stock';
        } elseif ($currentStock <= $this->min_stock_level) {
            return 'low_stock';
        } elseif ($currentStock >= $this->max_stock_level) {
            return 'overstock';
        } else {
            return 'in_stock';
        }
    }

    public function isLowStock(): bool
    {
        return $this->getCurrentStock() <= $this->min_stock_level;
    }

    public function isOutOfStock(): bool
    {
        return $this->getCurrentStock() <= 0;
    }

    public function getNearExpiryBatches(int $days = 30)
    {
        return $this->inventoryBatches()
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>', now())
            ->where('current_quantity', '>', 0)
            ->where('status', 'active')
            ->orderBy('expiry_date');
    }

    public function getExpiredBatches()
    {
        return $this->inventoryBatches()
            ->where('expiry_date', '<', now())
            ->where('current_quantity', '>', 0)
            ->where('status', 'active');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopePrescriptionRequired($query)
    {
        return $query->where('is_prescription_required', true);
    }

    public function scopeWithCurrentStock($query)
    {
        return $query->with(['inventoryBatches' => function ($q) {
            $q->where('expiry_date', '>', now())
              ->where('status', 'active');
        }]);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('(
            SELECT COALESCE(SUM(current_quantity), 0) 
            FROM inventory_batches 
            WHERE inventory_batches.product_id = products.id 
            AND expiry_date > NOW() 
            AND status = "active"
        ) <= min_stock_level');
    }

    public function scopeOutOfStock($query)
    {
        return $query->whereRaw('(
            SELECT COALESCE(SUM(current_quantity), 0) 
            FROM inventory_batches 
            WHERE inventory_batches.product_id = products.id 
            AND expiry_date > NOW() 
            AND status = "active"
        ) = 0');
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('product_category_id', $categoryId);
    }

    public function scopeByManufacturer($query, $manufacturer)
    {
        return $query->where('manufacturer', $manufacturer);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%")
              ->orWhere('barcode', 'like', "%{$search}%")
              ->orWhere('generic_name', 'like', "%{$search}%")
              ->orWhere('manufacturer', 'like', "%{$search}%");
        });
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        $name = $this->name;
        
        if ($this->strength) {
            $name .= " ({$this->strength})";
        }
        
        if ($this->dosage_form) {
            $name .= " - " . ucfirst($this->dosage_form);
        }
        
        return $name;
    }

    public function getStockStatusBadgeColorAttribute(): string
    {
        return match ($this->getStockStatus()) {
            'out_of_stock' => 'danger',
            'low_stock' => 'warning',
            'overstock' => 'info',
            default => 'success',
        };
    }

    // Boot method for model events
    protected static function boot()
    {
        parent::boot();

        // Auto-generate SKU if not provided
        static::creating(function ($product) {
            if (empty($product->sku)) {
                $product->sku = 'PRD-' . strtoupper(uniqid());
            }
            
            // Ensure SKU is uppercase
            $product->sku = strtoupper($product->sku);
        });

        static::updating(function ($product) {
            // Ensure SKU is uppercase
            $product->sku = strtoupper($product->sku);
        });
    }
}