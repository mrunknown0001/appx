<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\SalesPeriodSummaryWidget;
use App\Filament\Widgets\DetailedSalesBreakdownWidget;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            SalesPeriodSummaryWidget::class,
            DetailedSalesBreakdownWidget::class,
        ];
    }
}