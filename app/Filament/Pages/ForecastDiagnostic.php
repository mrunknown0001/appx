<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class ForecastDiagnostic extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Forecast Diagnostic';
    protected static ?string $title = 'Forecast Data Diagnostic Tool';
    protected static ?string $navigationGroup = 'Analytics';
    protected static ?int $navigationSort = 2;
    protected static bool $shouldRegisterNavigation = false; // Hidden by default

    protected static string $view = 'filament.pages.forecast-diagnostic';

    public $diagnosticData = null;

    public function mount(): void
    {
        $this->runDiagnostic();
    }

    public function runDiagnostic(): void
    {
        $this->diagnosticData = [
            'total_sales' => Sale::count(),
            'total_sale_items' => SaleItem::count(),
            'total_products' => Product::count(),
            'date_range' => $this->getDateRange(),
            'products_with_data' => $this->getProductsWithData(),
            'monthly_sales_count' => $this->getMonthlySalesCount(),
            'products_ready_for_forecast' => $this->getProductsReadyForForecast(),
        ];
    }

    private function getDateRange(): array
    {
        $oldest = SaleItem::orderBy('created_at', 'asc')->first();
        $newest = SaleItem::orderBy('created_at', 'desc')->first();

        return [
            'oldest' => $oldest ? $oldest->created_at->format('Y-m-d') : 'No data',
            'newest' => $newest ? $newest->created_at->format('Y-m-d') : 'No data',
            'days' => $oldest && $newest ? $oldest->created_at->diffInDays($newest->created_at) : 0,
        ];
    }

    private function getProductsWithData(): array
    {
        return DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                DB::raw('COUNT(DISTINCT DATE_FORMAT(sale_items.created_at, "%Y-%m")) as months'),
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('months', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    private function getMonthlySalesCount(): array
    {
        return DB::table('sale_items')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_price) as total_revenue')
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->toArray();
    }

    private function getProductsReadyForForecast(): array
    {
        $products = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                DB::raw('COUNT(DISTINCT DATE_FORMAT(sale_items.created_at, "%Y-%m")) as months')
            )
            ->groupBy('products.id', 'products.name')
            ->having('months', '>=', 3)
            ->get();

        return [
            'count' => $products->count(),
            'products' => $products->toArray(),
        ];
    }
}