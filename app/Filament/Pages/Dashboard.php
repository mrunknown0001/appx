<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\SalesPeriodSummaryWidget;
use App\Filament\Widgets\DetailedSalesBreakdownWidget;
use Illuminate\Support\Facades\Log;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.pages.dashboard';

    public bool $showInventoryAlertModal = false;

    public array $inventoryAlertSummary = [];

    public int $inventoryAlertTotal = 0;

    public ?string $inventoryAlertLastCalculatedAt = null;

    public function mount(): void
    {
        parent::mount();

        $summary = session()->pull('inventory_alert_summary', []);
        $total = (int) session()->pull('inventory_alert_total', 0);
        $timestamp = session()->pull('inventory_alert_last_calculated_at');

        Log::info('Inventory alert dashboard mount pulled session data', [
            'summary_is_array' => is_array($summary),
            'summary_keys' => is_array($summary) ? array_keys($summary) : null,
            'summary_counts' => is_array($summary) ? collect($summary)->map(fn ($section) => $section['count'] ?? null)->all() : null,
            'total_alerts' => $total,
            'timestamp_present' => ! empty($timestamp),
        ]);

        if ($total > 0 && ! empty($summary)) {
            $normalizedSummary = collect($summary)
                ->map(function ($section, $key) {
                    $items = $section['items'] ?? [];

                    if ($items instanceof \Illuminate\Support\Collection) {
                        $items = $items->map(fn ($item) => is_array($item) ? $item : (array) $item)->all();
                    } elseif (is_array($items)) {
                        $items = collect($items)->map(fn ($item) => is_array($item) ? $item : (array) $item)->all();
                    } else {
                        $items = [];
                    }

                    return [
                        'label' => (string) ($section['label'] ?? $key),
                        'severity' => (string) ($section['severity'] ?? 'warning'),
                        'description' => (string) ($section['description'] ?? ''),
                        'items' => $items,
                        'count' => (int) ($section['count'] ?? 0),
                    ];
                })
                ->filter(fn ($section) => ($section['count'] ?? 0) > 0)
                ->toArray();

            $this->inventoryAlertSummary = $normalizedSummary;
            $this->inventoryAlertTotal = collect($normalizedSummary)->sum('count');
            $this->inventoryAlertLastCalculatedAt = $timestamp;
            $this->showInventoryAlertModal = $this->inventoryAlertTotal > 0;

            if ($this->showInventoryAlertModal) {
                $this->dispatchBrowserEvent('open-modal', ['id' => 'inventory-alert-modal']);
            }

            Log::info('Inventory alert modal will be shown on dashboard', [
                'total_alerts' => $this->inventoryAlertTotal,
                'timestamp' => $this->inventoryAlertLastCalculatedAt,
            ]);
        } else {
            $this->showInventoryAlertModal = false;

            Log::info('Inventory alert modal skipped on dashboard', [
                'total_alerts' => $total,
                'summary_empty' => empty($summary),
            ]);
        }
    }

    public function getWidgets(): array
    {
        return [
            SalesPeriodSummaryWidget::class,
            DetailedSalesBreakdownWidget::class,
        ];
    }
}