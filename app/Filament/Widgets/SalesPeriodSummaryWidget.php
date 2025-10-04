<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesPeriodSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        // TEMPORARY DEBUG - Remove after fixing
        $debug = $this->debugSalesData();
        
        // Calculate period dates
        $now = Carbon::now();
        
        // Week to date (Monday to now)
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfDay();
        
        // Month to date
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfDay();
        
        // Year to date
        $yearStart = $now->copy()->startOfYear();
        $yearEnd = $now->copy()->endOfDay();

        // Get sales data for each period
        $weekSales = $this->getSalesData($weekStart, $weekEnd);
        $monthSales = $this->getSalesData($monthStart, $monthEnd);
        $yearSales = $this->getSalesData($yearStart, $yearEnd);

        // Get previous period data for comparison
        $prevWeekSales = $this->getSalesData(
            $weekStart->copy()->subWeek(), 
            $weekEnd->copy()->subWeek()
        );
        $prevMonthSales = $this->getSalesData(
            $monthStart->copy()->subMonth(), 
            $monthEnd->copy()->subMonth()
        );
        $prevYearSales = $this->getSalesData(
            $yearStart->copy()->subYear(), 
            $yearEnd->copy()->subYear()
        );

        return [
            Stat::make('Week to Date Sales', '₱' . number_format($weekSales['revenue'], 2))
                ->description($this->getChangeDescription($weekSales['revenue'], $prevWeekSales['revenue']))
                ->descriptionIcon($this->getChangeIcon($weekSales['revenue'], $prevWeekSales['revenue']))
                ->color($this->getChangeColor($weekSales['revenue'], $prevWeekSales['revenue']))
                ->chart($this->getWeeklyChart())
                ->extraAttributes([
                    'class' => 'bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20',
                ]),

            Stat::make('Month to Date Sales', '₱' . number_format($monthSales['revenue'], 2))
                ->description($this->getChangeDescription($monthSales['revenue'], $prevMonthSales['revenue']))
                ->descriptionIcon($this->getChangeIcon($monthSales['revenue'], $prevMonthSales['revenue']))
                ->color($this->getChangeColor($monthSales['revenue'], $prevMonthSales['revenue']))
                ->chart($this->getMonthlyChart())
                ->extraAttributes([
                    'class' => 'bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20',
                ]),

            Stat::make('Year to Date Sales', '₱' . number_format($yearSales['revenue'], 2))
                ->description($this->getChangeDescription($yearSales['revenue'], $prevYearSales['revenue']))
                ->descriptionIcon($this->getChangeIcon($yearSales['revenue'], $prevYearSales['revenue']))
                ->color($this->getChangeColor($yearSales['revenue'], $prevYearSales['revenue']))
                ->chart($this->getYearlyChart())
                ->extraAttributes([
                    'class' => 'bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20',
                ]),

            // Additional metrics row
            // Stat::make('WTD Transactions', number_format($weekSales['transactions']))
            //     ->description('Avg: ₱' . number_format($weekSales['transactions'] > 0 ? $weekSales['revenue'] / $weekSales['transactions'] : 0, 2))
            //     ->descriptionIcon('heroicon-m-calculator')
            //     ->color('gray'),

            // Stat::make('MTD Transactions', number_format($monthSales['transactions']))
            //     ->description('Avg: ₱' . number_format($monthSales['transactions'] > 0 ? $monthSales['revenue'] / $monthSales['transactions'] : 0, 2))
            //     ->descriptionIcon('heroicon-m-calculator')
            //     ->color('gray'),

            // Stat::make('YTD Transactions', number_format($yearSales['transactions']))
            //     ->description('Avg: ₱' . number_format($yearSales['transactions'] > 0 ? $yearSales['revenue'] / $yearSales['transactions'] : 0, 2))
            //     ->descriptionIcon('heroicon-m-calculator')
            //     ->color('gray'),
        ];
    }

    private function getSalesData(Carbon $startDate, Carbon $endDate): array
    {
        // Make sure we're using the full datetime range
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();
        
        $sales = Sale::whereBetween('sale_date', [$start, $end])
            ->where('status', 'completed')
            ->selectRaw('
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_transaction
            ')
            ->first();

        return [
            'revenue' => $sales->total_revenue ?? 0,
            'transactions' => $sales->transaction_count ?? 0,
            'average' => $sales->avg_transaction ?? 0,
        ];
    }

    private function getChangeDescription(float $current, float $previous): string
    {
        if ($previous == 0) {
            return $current > 0 ? 'New sales period' : 'No sales yet';
        }

        $change = (($current - $previous) / $previous) * 100;
        $changeText = abs($change) < 0.1 ? 'No change' : 
                     ($change > 0 ? '+' . number_format($change, 1) . '%' : number_format($change, 1) . '%');
        
        return $changeText . ' from last period';
    }

    private function getChangeIcon(float $current, float $previous): string
    {
        if ($previous == 0) return 'heroicon-m-minus';
        
        $change = $current - $previous;
        return $change > 0 ? 'heroicon-m-arrow-trending-up' : 
               ($change < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus');
    }

    private function getChangeColor(float $current, float $previous): string
    {
        if ($previous == 0) return 'gray';
        
        $change = $current - $previous;
        return $change > 0 ? 'success' : ($change < 0 ? 'danger' : 'gray');
    }

    private function getWeeklyChart(): array
    {
        $weekStart = Carbon::now()->startOfWeek();
        $data = [];
        
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $sales = Sale::whereDate('sale_date', $day->format('Y-m-d'))
                ->where('status', 'completed')
                ->sum('total_amount') ?? 0;
            $data[] = (float) $sales;
        }
        
        return $data;
    }

    private function getMonthlyChart(): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $data = [];
        $days = $monthStart->daysInMonth;
        
        // Sample every few days for the chart (to keep it readable)
        $interval = max(1, floor($days / 10));
        
        for ($i = 1; $i <= $days; $i += $interval) {
            $day = $monthStart->copy()->addDays($i - 1);
            $sales = Sale::whereDate('sale_date', $day->format('Y-m-d'))
                ->where('status', 'completed')
                ->sum('total_amount') ?? 0;
            $data[] = (float) $sales;
        }
        
        return $data;
    }

    private function getYearlyChart(): array
    {
        $data = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $sales = Sale::whereYear('sale_date', Carbon::now()->year)
                ->whereMonth('sale_date', $month)
                ->where('status', 'completed')
                ->sum('total_amount') ?? 0;
            $data[] = (float) $sales;
        }
        
        return $data;
    }

    protected function getPollingInterval(): ?string
    {
        return '30s'; // Refresh every 30 seconds
    }

    // TEMPORARY DEBUG METHOD - Remove after fixing
    private function debugSalesData(): array
    {
        $now = Carbon::now();
        
        // Week to date (Monday to now)
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfDay();
        
        // Month to date
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfDay();
        
        $weekSales = $this->getSalesData($weekStart, $weekEnd);
        $monthSales = $this->getSalesData($monthStart, $monthEnd);
        
        // Log debug info
        \Log::info('Sales Widget Debug', [
            'current_time' => $now->format('Y-m-d H:i:s T'),
            'week_start' => $weekStart->format('Y-m-d H:i:s T'),
            'week_end' => $weekEnd->format('Y-m-d H:i:s T'),
            'month_start' => $monthStart->format('Y-m-d H:i:s T'),
            'month_end' => $monthEnd->format('Y-m-d H:i:s T'),
            'week_sales' => $weekSales,
            'month_sales' => $monthSales,
        ]);
        
        return [
            'week' => $weekSales,
            'month' => $monthSales,
        ];
    }
}