<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class CustomerSatisfactionChart extends ChartWidget
{
    protected static ?string $heading = 'Customer Satisfaction';
    protected static string $color = 'success';
    protected static ?int $sort = 6;

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'data' => [156, 89, 45, 23, 12],
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(147, 51, 234, 0.8)',
                    ],
                ],
            ],
            'labels' => ['Excellent', 'Good', 'Average', 'Poor', 'Terrible'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
