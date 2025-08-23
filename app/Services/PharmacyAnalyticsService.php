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
            ->join('product_categories', 'products.product_category_id', '=', 'product_categories.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', 'completed')
            ->selectRaw('
                product_categories.name as category_name,
                SUM(sale_items.quantity) as total_quantity,
                SUM(sale_items.total_price) as total_revenue
            ')
            ->groupBy('product_categories.id', 'product_categories.name')
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


    // Added Code

    /**
     * Get sales summary for different time periods
     */
    public function getSalesPeriodSummary()
    {
        $now = Carbon::now();
        
        return [
            'week_to_date' => $this->getWeekToDateSales(),
            'month_to_date' => $this->getMonthToDateSales(), 
            'year_to_date' => $this->getYearToDateSales(),
            'comparisons' => $this->getPeriodComparisons(),
            'trends' => $this->getSalesTrends(),
        ];
    }

    /**
     * Get week to date sales
     */
    public function getWeekToDateSales()
    {
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfDay();
        
        $current = $this->getPeriodSalesData($weekStart, $weekEnd);
        $previous = $this->getPeriodSalesData(
            $weekStart->copy()->subWeek(),
            $weekEnd->copy()->subWeek()
        );
        
        return [
            'period_name' => 'Week to Date',
            'current' => $current,
            'previous' => $previous,
            'change_percent' => $this->calculateChangePercent($current['revenue'], $previous['revenue']),
            'daily_breakdown' => $this->getWeeklyBreakdown($weekStart, $weekEnd),
            'top_products' => $this->getTopSellingProducts($weekStart, $weekEnd, 5),
        ];
    }

    /**
     * Get month to date sales
     */
    public function getMonthToDateSales()
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfDay();
        
        $current = $this->getPeriodSalesData($monthStart, $monthEnd);
        $previous = $this->getPeriodSalesData(
            $monthStart->copy()->subMonth(),
            $monthEnd->copy()->subMonth()
        );
        
        return [
            'period_name' => 'Month to Date',
            'current' => $current,
            'previous' => $previous,
            'change_percent' => $this->calculateChangePercent($current['revenue'], $previous['revenue']),
            'daily_breakdown' => $this->getMonthlyBreakdown($monthStart, $monthEnd),
            'top_products' => $this->getTopSellingProducts($monthStart, $monthEnd, 5),
            'category_breakdown' => $this->getSalesByCategory($monthStart, $monthEnd),
        ];
    }

    /**
     * Get year to date sales
     */
    public function getYearToDateSales()
    {
        $yearStart = Carbon::now()->startOfYear();
        $yearEnd = Carbon::now()->endOfDay();
        
        $current = $this->getPeriodSalesData($yearStart, $yearEnd);
        $previous = $this->getPeriodSalesData(
            $yearStart->copy()->subYear(),
            $yearEnd->copy()->subYear()
        );
        
        return [
            'period_name' => 'Year to Date',
            'current' => $current,
            'previous' => $previous,
            'change_percent' => $this->calculateChangePercent($current['revenue'], $previous['revenue']),
            'monthly_breakdown' => $this->getYearlyBreakdown($yearStart, $yearEnd),
            'top_products' => $this->getTopSellingProducts($yearStart, $yearEnd, 10),
            'category_breakdown' => $this->getSalesByCategory($yearStart, $yearEnd),
            'quarterly_summary' => $this->getQuarterlySummary(),
        ];
    }

    /**
     * Get period sales data with detailed metrics
     */
    private function getPeriodSalesData(Carbon $startDate, Carbon $endDate): array
    {
        // Ensure we capture the full datetime range
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();
        
        $salesData = Sale::whereBetween('sale_date', [$start, $end])
            ->where('status', 'completed')
            ->selectRaw('
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_transaction,
                MIN(total_amount) as min_transaction,
                MAX(total_amount) as max_transaction
            ')
            ->first();

        $itemsData = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->where('sales.status', 'completed')
            ->selectRaw('
                SUM(quantity) as total_items_sold,
                COUNT(DISTINCT product_id) as unique_products_sold
            ')
            ->first();

        return [
            'revenue' => (float) ($salesData->total_revenue ?? 0),
            'transactions' => (int) ($salesData->transaction_count ?? 0),
            'avg_transaction' => (float) ($salesData->avg_transaction ?? 0),
            'min_transaction' => (float) ($salesData->min_transaction ?? 0),
            'max_transaction' => (float) ($salesData->max_transaction ?? 0),
            'items_sold' => (int) ($itemsData->total_items_sold ?? 0),
            'unique_products' => (int) ($itemsData->unique_products_sold ?? 0),
        ];
    }

    /**
     * Get weekly breakdown for charts
     */
    private function getWeeklyBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $current = $startDate->copy();
        
        while ($current->lte($endDate)) {
            $dayData = Sale::whereDate('sale_date', $current->format('Y-m-d'))
                ->where('status', 'completed')
                ->selectRaw('
                    COUNT(*) as transactions,
                    COALESCE(SUM(total_amount), 0) as revenue
                ')
                ->first();
                
            $data[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->format('l'),
                'day_short' => $current->format('D'),
                'transactions' => $dayData->transactions ?? 0,
                'revenue' => $dayData->revenue ?? 0,
            ];
            
            $current->addDay();
        }
        
        return $data;
    }

    /**
     * Get monthly breakdown for charts
     */
    private function getMonthlyBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();
        
        return Sale::whereBetween('sale_date', [$start, $end])
            ->where('status', 'completed')
            ->selectRaw('
                DATE(sale_date) as date,
                COUNT(*) as transactions,
                SUM(total_amount) as revenue
            ')
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'transactions' => (int) $item->transactions,
                    'revenue' => (float) $item->revenue,
                ];
            })
            ->toArray();
    }

    /**
     * Get yearly breakdown by month
     */
    private function getYearlyBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $monthData = Sale::whereYear('sale_date', $startDate->year)
                ->whereMonth('sale_date', $month)
                ->where('status', 'completed')
                ->selectRaw('
                    COUNT(*) as transactions,
                    COALESCE(SUM(total_amount), 0) as revenue
                ')
                ->first();
                
            $data[] = [
                'month' => $month,
                'month_name' => Carbon::create()->month($month)->format('F'),
                'month_short' => Carbon::create()->month($month)->format('M'),
                'transactions' => $monthData->transactions ?? 0,
                'revenue' => $monthData->revenue ?? 0,
            ];
        }
        
        return $data;
    }

    /**
     * Get quarterly summary for year to date
     */
    private function getQuarterlySummary(): array
    {
        $quarters = [
            'Q1' => ['months' => [1, 2, 3], 'name' => 'Q1 (Jan-Mar)'],
            'Q2' => ['months' => [4, 5, 6], 'name' => 'Q2 (Apr-Jun)'],
            'Q3' => ['months' => [7, 8, 9], 'name' => 'Q3 (Jul-Sep)'],
            'Q4' => ['months' => [10, 11, 12], 'name' => 'Q4 (Oct-Dec)'],
        ];
        
        $data = [];
        $currentYear = Carbon::now()->year;
        
        foreach ($quarters as $quarterKey => $quarter) {
            $quarterData = Sale::whereYear('sale_date', $currentYear)
                ->whereIn(DB::raw('MONTH(sale_date)'), $quarter['months'])
                ->where('status', 'completed')
                ->selectRaw('
                    COUNT(*) as transactions,
                    COALESCE(SUM(total_amount), 0) as revenue
                ')
                ->first();
                
            $data[] = [
                'quarter' => $quarterKey,
                'name' => $quarter['name'],
                'months' => $quarter['months'],
                'transactions' => $quarterData->transactions ?? 0,
                'revenue' => $quarterData->revenue ?? 0,
            ];
        }
        
        return $data;
    }

    /**
     * Get period comparisons with insights
     */
    public function getPeriodComparisons(): array
    {
        $wtd = $this->getWeekToDateSales();
        $mtd = $this->getMonthToDateSales();
        $ytd = $this->getYearToDateSales();
        
        return [
            'periods' => [
                'week' => $wtd['current'],
                'month' => $mtd['current'],
                'year' => $ytd['current'],
            ],
            'growth_rates' => [
                'week' => $wtd['change_percent'],
                'month' => $mtd['change_percent'],
                'year' => $ytd['change_percent'],
            ],
            'performance_insights' => $this->generatePerformanceInsights($wtd, $mtd, $ytd),
        ];
    }

    /**
     * Generate performance insights
     */
    private function generatePerformanceInsights($wtd, $mtd, $ytd): array
    {
        $insights = [];
        
        // Revenue growth insights
        if ($wtd['change_percent'] > 10) {
            $insights[] = [
                'type' => 'positive',
                'message' => 'Strong weekly growth of ' . number_format($wtd['change_percent'], 1) . '%'
            ];
        } elseif ($wtd['change_percent'] < -10) {
            $insights[] = [
                'type' => 'warning',
                'message' => 'Weekly sales declined by ' . number_format(abs($wtd['change_percent']), 1) . '%'
            ];
        }
        
        // Transaction volume insights
        if ($mtd['current']['transactions'] > 0 && $wtd['current']['transactions'] > 0) {
            $weeklyRunRate = ($wtd['current']['transactions'] / 7) * 30.4; // Avg days in month
            $monthlyPace = ($mtd['current']['transactions'] / Carbon::now()->day) * Carbon::now()->daysInMonth;
            
            if ($weeklyRunRate > $monthlyPace * 1.1) {
                $insights[] = [
                    'type' => 'positive',
                    'message' => 'Transaction volume trending above monthly pace'
                ];
            }
        }
        
        // Average transaction insights
        if ($mtd['current']['avg_transaction'] > $ytd['current']['avg_transaction'] * 1.1) {
            $insights[] = [
                'type' => 'positive',
                'message' => 'Average transaction value is above yearly average'
            ];
        }
        
        return $insights;
    }

    /**
     * Calculate percentage change between periods
     */
    private function calculateChangePercent(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return (($current - $previous) / $previous) * 100;
    }

    /**
     * Get sales trends analysis
     */
    public function getSalesTrends(): array
    {
        // Get last 4 weeks of data for trend analysis
        $trends = [];
        
        for ($week = 3; $week >= 0; $week--) {
            $weekStart = Carbon::now()->subWeeks($week)->startOfWeek();
            $weekEnd = Carbon::now()->subWeeks($week)->endOfWeek();
            
            $weekData = $this->getPeriodSalesData($weekStart, $weekEnd);
            
            $trends[] = [
                'period' => $weekStart->format('M j') . ' - ' . $weekEnd->format('M j'),
                'week_number' => $weekStart->weekOfYear,
                'revenue' => $weekData['revenue'],
                'transactions' => $weekData['transactions'],
                'avg_transaction' => $weekData['avg_transaction'],
            ];
        }
        
        return [
            'weekly_trends' => $trends,
            'trend_direction' => $this->calculateTrendDirection($trends),
            'volatility' => $this->calculateVolatility($trends),
        ];
    }

    /**
     * Calculate trend direction
     */
    private function calculateTrendDirection(array $trends): string
    {
        if (count($trends) < 2) return 'insufficient_data';
        
        $revenues = array_column($trends, 'revenue');
        $increases = 0;
        $decreases = 0;
        
        for ($i = 1; $i < count($revenues); $i++) {
            if ($revenues[$i] > $revenues[$i-1]) $increases++;
            elseif ($revenues[$i] < $revenues[$i-1]) $decreases++;
        }
        
        if ($increases > $decreases) return 'upward';
        if ($decreases > $increases) return 'downward';
        return 'stable';
    }

    /**
     * Calculate revenue volatility
     */
    private function calculateVolatility(array $trends): float
    {
        if (count($trends) < 2) return 0;
        
        $revenues = array_column($trends, 'revenue');
        $mean = array_sum($revenues) / count($revenues);
        
        $variance = 0;
        foreach ($revenues as $revenue) {
            $variance += pow($revenue - $mean, 2);
        }
        
        $variance = $variance / count($revenues);
        $standardDeviation = sqrt($variance);
        
        return $mean > 0 ? ($standardDeviation / $mean) * 100 : 0; // Coefficient of variation as percentage
    }
}