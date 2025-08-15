<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class SalesAnalyticsChart extends ChartWidget
{
    protected static ?string $heading = 'Sales Analytics';
    protected static ?int $sort = 8;

    public ?string $filter = 'today';

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Today',
            'week' => 'Last week',
            'month' => 'Last month',
            'year' => 'This year',
        ];
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter;

        $data = match ($activeFilter) {
            'today' => [120, 150, 180, 200, 175, 190, 210],
            'week' => [850, 920, 1080, 1150, 980, 1220, 1350],
            'month' => [3200, 3800, 4100, 3900, 4500, 4200, 4800, 5100, 4700, 5300, 5600, 5200],
            'year' => [45000, 52000, 48000, 58000, 62000, 59000, 67000, 71000, 68000, 74000, 78000, 75000],
            default => [120, 150, 180, 200, 175, 190, 210],
        };

        $labels = match ($activeFilter) {
            'today' => ['6 AM', '9 AM', '12 PM', '3 PM', '6 PM', '9 PM', '12 AM'],
            'week' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'month' => range(1, count($data)),
            'year' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            default => ['6 AM', '9 AM', '12 PM', '3 PM', '6 PM', '9 PM', '12 AM'],
        };

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => $data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
