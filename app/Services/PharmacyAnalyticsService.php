<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\InventoryBatch;
use App\Models\StockEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PharmacyAnalyticsService
{
    /**
     * Get sales analytics for specified period
     */
    public function getSalesAnalytics($startDate, $endDate)
    {
        return [
            'total_sales' => $this->getTotalSales($startDate, $endDate),
            'sales_by_product' => $this->getSalesByProduct($startDate, $endDate),
            'sales_by_category' => $this->getSalesByCategory($startDate, $endDate),
            'daily_sales' => $this->getDailySales($startDate, $endDate),
            'top_selling_products' => $this->getTopSellingProducts($startDate, $endDate),
        ];
    }

    /**
     * Get stock movement analytics
     */
    public function getStockMovementAnalytics($startDate, $endDate)
    {
        return [
            'stock_ins' => $this->getStockEntries($startDate, $endDate),
            'stock_outs' => $this->getStockOuts($startDate, $endDate),
            'low_stock_products' => $this->getLowStockProducts(),
            'expired_products' => $this->getExpiredProducts(),
            'near_expiry_products' => $this->getNearExpiryProducts(),
        ];
    }

    /**
     * Generate sales forecast based on historical data
     */
    public function generateSalesForecast($productId = null, $months = 3)
    {
        $query = SaleItem::with('product')
            ->selectRaw('
                product_id,
                YEAR(created_at) as year,
                MONTH(created_at) as month,
                SUM(quantity) as total_quantity,
                SUM(total_price) as total_revenue
            ')
            ->groupBy('product_id', 'year', 'month')
            ->orderBy('year')
            ->orderBy('month');

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $historicalData = $query->get();
        
        // Simple moving average forecast
        $forecasts = [];
        foreach ($historicalData->groupBy('product_id') as $productId => $productData) {
            $quantities = $productData->pluck('total_quantity')->toArray();
            $revenues = $productData->pluck('total_revenue')->toArray();
            
            // Calculate moving averages for forecasting
            $avgQuantity = collect($quantities)->slice(-3)->avg(); // Last 3 months average
            $avgRevenue = collect($revenues)->slice(-3)->avg();
            
            $forecasts[$productId] = [
                'product' => Product::find($productId),
                'forecasted_quantity' => round($avgQuantity * $months),
                'forecasted_revenue' => round($avgRevenue * $months, 2),
                'historical_data' => $productData
            ];
        }

        return $forecasts;
    }

    /**
     * Generate restock quantity forecast
     */
    public function generateRestockForecast($productId = null)
    {
        $query = Product::with(['inventoryBatches', 'saleItems'])
            ->select('products.*')
            ->selectRaw('
                (SELECT SUM(current_quantity) 
                 FROM inventory_batches 
                 WHERE product_id = products.id 
                 AND expiry_date > NOW() 
                 AND status = "active") as current_stock
            ')
            ->selectRaw('
                (SELECT AVG(daily_sales.quantity) 
                 FROM (
                    SELECT DATE(sale_items.created_at) as sale_date, 
                           SUM(sale_items.quantity) as quantity
                    FROM sale_items 
                    WHERE sale_items.product_id = products.id 
                    AND sale_items.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(sale_items.created_at)
                 ) as daily_sales) as avg_daily_sales
            ');

        if ($productId) {
            $query->where('id', $productId);
        }

        $products = $query->get();

        $restockForecasts = [];
        foreach ($products as $product) {
            $avgDailySales = $product->avg_daily_sales ?? 0;
            $currentStock = $product->current_stock ?? 0;
            $minStockLevel = $product->min_stock_level;
            
            // Calculate days until stock runs out
            $daysUntilStockOut = $avgDailySales > 0 ? $currentStock / $avgDailySales : 999;
            
            // Suggest restock if stock will run out in less than 7 days or below min level
            $shouldRestock = $daysUntilStockOut <= 7 || $currentStock <= $minStockLevel;
            
            // Suggested restock quantity (30 days worth of sales)
            $suggestedRestockQty = max($avgDailySales * 30, $product->max_stock_level - $currentStock);

            $restockForecasts[] = [
                'product' => $product,
                'current_stock' => $currentStock,
                'avg_daily_sales' => round($avgDailySales, 2),
                'days_until_stockout' => round($daysUntilStockOut, 1),
                'should_restock' => $shouldRestock,
                'suggested_restock_quantity' => round($suggestedRestockQty),
                'urgency' => $this->getRestockUrgency($daysUntilStockOut, $currentStock, $minStockLevel)
            ];
        }

        return collect($restockForecasts)->sortByDesc('urgency');
    }

    private function getTotalSales($startDate, $endDate)
    {
        return Sale::whereBetween('sale_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('total_amount');
    }

    private function getSalesByProduct($startDate, $endDate)
    {
        return SaleItem::with('product')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', 'completed')
            ->selectRaw('
                product_id,
                SUM(sale_items.quantity) as total_quantity,
                SUM(sale_items.total_price) as total_revenue
            ')
            ->groupBy('product_id')
            ->orderByDesc('total_revenue')
            ->get();
    }

    private function getSalesByCategory($startDate, $endDate)
    {
        return SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', 'completed')
            ->selectRaw('
                categories.name as category_name,
                SUM(sale_items.quantity) as total_quantity,
                SUM(sale_items.total_price) as total_revenue
            ')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();
    }

    private function getDailySales($startDate, $endDate)
    {
        return Sale::whereBetween('sale_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->selectRaw('
                DATE(sale_date) as sale_date,
                COUNT(*) as transaction_count,
                SUM(total_amount) as daily_revenue
            ')
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->orderBy('sale_date')
            ->get();
    }

    private function getTopSellingProducts($startDate, $endDate, $limit = 10)
    {
        return SaleItem::with('product')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', 'completed')
            ->selectRaw('
                product_id,
                SUM(sale_items.quantity) as total_quantity
            ')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }

    private function getStockEntries($startDate, $endDate)
    {
        return StockEntry::with('product')
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->selectRaw('
                product_id,
                SUM(quantity_received) as total_quantity,
                SUM(total_cost) as total_cost
            ')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->get();
    }

    private function getStockOuts($startDate, $endDate)
    {
        return SaleItem::with('product')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', 'completed')
            ->selectRaw('
                product_id,
                SUM(quantity) as total_quantity_sold
            ')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity_sold')
            ->get();
    }

    private function getLowStockProducts()
    {
        return Product::select('products.*')
            ->selectRaw('
                (SELECT SUM(current_quantity) 
                 FROM inventory_batches 
                 WHERE product_id = products.id 
                 AND expiry_date > NOW() 
                 AND status = "active") as current_stock
            ')
            ->havingRaw('current_stock <= min_stock_level OR current_stock IS NULL')
            ->orderBy('min_stock_level', 'desc')
            ->get();
    }

    private function getExpiredProducts()
    {
        return InventoryBatch::with('product')
            ->where('expiry_date', '<', Carbon::now())
            ->where('current_quantity', '>', 0)
            ->where('status', 'active')
            ->orderBy('expiry_date')
            ->get();
    }

    private function getNearExpiryProducts($days = 30)
    {
        return InventoryBatch::with('product')
            ->where('expiry_date', '<=', Carbon::now()->addDays($days))
            ->where('expiry_date', '>', Carbon::now())
            ->where('current_quantity', '>', 0)
            ->where('status', 'active')
            ->orderBy('expiry_date')
            ->get();
    }

    private function getRestockUrgency($daysUntilStockOut, $currentStock, $minStockLevel)
    {
        if ($currentStock <= 0) return 5; // Critical - Out of stock
        if ($daysUntilStockOut <= 1) return 4; // Urgent - 1 day or less
        if ($daysUntilStockOut <= 3) return 3; // High - 3 days or less
        if ($daysUntilStockOut <= 7 || $currentStock <= $minStockLevel) return 2; // Medium
        return 1; // Low
    }
}