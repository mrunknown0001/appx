<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class ProductMetricsChart extends ChartWidget
{
    protected static ?string $heading = 'Product Performance Metrics';
    protected static string $color = 'info';
    protected static ?int $sort = 7;

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Product A',
                    'data' => [8, 7, 9, 6, 8, 7],
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                ],
                [
                    'label' => 'Product B',
                    'data' => [6, 9, 7, 8, 6, 9],
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                ],
            ],
            'labels' => ['Quality', 'Price', 'Features', 'Support', 'Delivery', 'Rating'],
        ];
    }

    protected function getType(): string
    {
        return 'radar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'r' => [
                    'beginAtZero' => true,
                    'max' => 10,
                ],
            ],
        ];
    }
}
