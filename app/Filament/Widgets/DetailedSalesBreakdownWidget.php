<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Services\PharmacyAnalyticsService;
use Carbon\Carbon;

class DetailedSalesBreakdownWidget extends Widget
{
    protected static string $view = 'filament.widgets.detailed-sales-breakdown';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    
    public ?string $selectedPeriod = 'month';

    public function getViewData(): array
    {
        $analyticsService = app(PharmacyAnalyticsService::class);
        
        return match($this->selectedPeriod) {
            'week' => $this->getWeekData($analyticsService),
            'month' => $this->getMonthData($analyticsService),
            'year' => $this->getYearData($analyticsService),
            default => $this->getMonthData($analyticsService),
        };
    }

    private function getWeekData(PharmacyAnalyticsService $service): array
    {
        $data = $service->getWeekToDateSales();
        
        return [
            'title' => 'Week to Date Analysis',
            'period_data' => $data,
            'chart_data' => $this->formatChartData($data['daily_breakdown']),
            'insights' => $this->generateWeeklyInsights($data),
            'comparison_data' => $this->formatComparisonData($data['current'], $data['previous']),
        ];
    }

    private function getMonthData(PharmacyAnalyticsService $service): array
    {
        $data = $service->getMonthToDateSales();
        
        return [
            'title' => 'Month to Date Analysis',
            'period_data' => $data,
            'chart_data' => $this->formatChartData($data['daily_breakdown']),
            'insights' => $this->generateMonthlyInsights($data),
            'comparison_data' => $this->formatComparisonData($data['current'], $data['previous']),
        ];
    }

    private function getYearData(PharmacyAnalyticsService $service): array
    {
        $data = $service->getYearToDateSales();
        
        return [
            'title' => 'Year to Date Analysis',
            'period_data' => $data,
            'chart_data' => $this->formatMonthlyChartData($data['monthly_breakdown']),
            'insights' => $this->generateYearlyInsights($data),
            'comparison_data' => $this->formatComparisonData($data['current'], $data['previous']),
            'quarterly_data' => $data['quarterly_summary'] ?? [],
        ];
    }

    private function formatChartData(array $breakdown): array
    {
        return [
            'labels' => array_map(fn($item) => $item['day_short'] ?? date('M j', strtotime($item['date'])), $breakdown),
            'revenue' => array_column($breakdown, 'revenue'),
            'transactions' => array_column($breakdown, 'transactions'),
        ];
    }

    private function formatMonthlyChartData(array $breakdown): array
    {
        return [
            'labels' => array_column($breakdown, 'month_short'),
            'revenue' => array_column($breakdown, 'revenue'),
            'transactions' => array_column($breakdown, 'transactions'),
        ];
    }

    private function formatComparisonData(array $current, array $previous): array
    {
        return [
            'revenue_change' => $this->calculateChange($current['revenue'], $previous['revenue']),
            'transaction_change' => $this->calculateChange($current['transactions'], $previous['transactions']),
            'avg_transaction_change' => $this->calculateChange($current['avg_transaction'], $previous['avg_transaction']),
            'items_change' => $this->calculateChange($current['items_sold'], $previous['items_sold']),
        ];
    }

    private function calculateChange(float $current, float $previous): array
    {
        if ($previous == 0) {
            return [
                'value' => $current,
                'percentage' => $current > 0 ? 100 : 0,
                'direction' => $current > 0 ? 'up' : 'neutral',
                'formatted' => $current > 0 ? '+100%' : '0%',
            ];
        }

        $change = $current - $previous;
        $percentage = ($change / $previous) * 100;

        return [
            'value' => $change,
            'percentage' => $percentage,
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
            'formatted' => ($change >= 0 ? '+' : '') . number_format($percentage, 1) . '%',
        ];
    }

    private function generateWeeklyInsights(array $data): array
    {
        $insights = [];
        $current = $data['current'];
        $breakdown = $data['daily_breakdown'];

        // Peak day analysis
        if (!empty($breakdown)) {
            $peakDay = collect($breakdown)->sortByDesc('revenue')->first();
            $insights[] = [
                'type' => 'info',
                'title' => 'Peak Sales Day',
                'message' => "{$peakDay['day']} generated the highest revenue of ₱" . number_format($peakDay['revenue'], 2),
                'icon' => 'heroicon-o-chart-bar-square',
            ];
        }

        // Weekly performance
        if ($data['change_percent'] > 20) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Exceptional Growth',
                'message' => 'Weekly revenue increased by ' . number_format($data['change_percent'], 1) . '%',
                'icon' => 'heroicon-o-arrow-trending-up',
            ];
        } elseif ($data['change_percent'] < -20) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Significant Decline',
                'message' => 'Weekly revenue decreased by ' . number_format(abs($data['change_percent']), 1) . '%',
                'icon' => 'heroicon-o-arrow-trending-down',
            ];
        }

        return $insights;
    }

    private function generateMonthlyInsights(array $data): array
    {
        $insights = [];
        $current = $data['current'];

        // Monthly progress
        $daysInMonth = Carbon::now()->daysInMonth;
        $daysPassed = Carbon::now()->day;
        $progressPercentage = ($daysPassed / $daysInMonth) * 100;
        
        $insights[] = [
            'type' => 'info',
            'title' => 'Monthly Progress',
            'message' => number_format($progressPercentage, 1) . "% of the month completed ({$daysPassed}/{$daysInMonth} days)",
            'icon' => 'heroicon-o-calendar-days',
        ];

        // Run rate analysis
        if ($current['revenue'] > 0 && $daysPassed > 0) {
            $dailyAverage = $current['revenue'] / $daysPassed;
            $projectedMonthly = $dailyAverage * $daysInMonth;
            
            $insights[] = [
                'type' => 'info',
                'title' => 'Projected Monthly Total',
                'message' => 'At current pace: ₱' . number_format($projectedMonthly, 2),
                'icon' => 'heroicon-o-calculator',
            ];
        }

        // Top products insight
        if (!empty($data['top_products'][0])) {
            $topProduct = $data['top_products'][0];
            $insights[] = [
                'type' => 'success',
                'title' => 'Best Seller',
                'message' => $topProduct->product->name . ' (₱' . number_format($topProduct->total_revenue, 2) . ')',
                'icon' => 'heroicon-o-trophy',
            ];
        }

        return $insights;
    }

    private function generateYearlyInsights(array $data): array
    {
        $insights = [];
        $current = $data['current'];
        $quarterly = $data['quarterly_summary'] ?? [];

        // Year progress
        $dayOfYear = Carbon::now()->dayOfYear;
        $daysInYear = Carbon::now()->isLeapYear() ? 366 : 365;
        $yearProgress = ($dayOfYear / $daysInYear) * 100;

        $insights[] = [
            'type' => 'info',
            'title' => 'Year Progress',
            'message' => number_format($yearProgress, 1) . "% of the year completed",
            'icon' => 'heroicon-o-calendar',
        ];

        // Best quarter
        if (!empty($quarterly)) {
            $bestQuarter = collect($quarterly)->sortByDesc('revenue')->first();
            if ($bestQuarter['revenue'] > 0) {
                $insights[] = [
                    'type' => 'success',
                    'title' => 'Best Quarter',
                    'message' => $bestQuarter['name'] . ' with ₱' . number_format($bestQuarter['revenue'], 2),
                    'icon' => 'heroicon-o-star',
                ];
            }
        }

        // Growth insight
        if ($data['change_percent'] > 15) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Strong Annual Growth',
                'message' => 'Revenue up ' . number_format($data['change_percent'], 1) . '% vs last year',
                'icon' => 'heroicon-o-arrow-trending-up',
            ];
        }

        return $insights;
    }

    public function setPeriod(string $period): void
    {
        $this->selectedPeriod = $period;
    }
}