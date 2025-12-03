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

    public string $inventoryAlertBannerMessage = '';

    public bool $shouldShowInventoryAlertBanner = false;

    public bool $inventoryAlertBannerDismissed = false;

    public array $inventoryAlertCounts = [];


    public function mount(): void
    {

        // parent::mount();

        $summary = session()->pull('inventory_alert_summary', []);
        $total = (int) session()->pull('inventory_alert_total', 0);
        $timestamp = session()->pull('inventory_alert_last_calculated_at');
        $bannerAlreadyDismissed = (bool) session()->get('inventory_alert_flash_dismissed', false);

        $this->inventoryAlertBannerDismissed = $bannerAlreadyDismissed;
        $this->shouldShowInventoryAlertBanner = false;
        $this->inventoryAlertCounts = [];
        $bannerAlreadyDismissed = (bool) session()->get('inventory_alert_flash_dismissed', false);

        $this->inventoryAlertBannerDismissed = $bannerAlreadyDismissed;
        $this->shouldShowInventoryAlertBanner = false;
        $this->inventoryAlertCounts = [];

        $themeCookie = request()->cookie('filament_app_theme');
        $themeSession = session('filament_app_theme');
        $prefersDarkTheme = in_array('dark', [$themeCookie, $themeSession], true);

        $this->inventoryAlertBannerMessage = '';

        if ($total > 0 && ! empty($summary)) {
            $counts = [
                'out_of_stock' => (int) ($summary['out_of_stock']['count'] ?? 0),
                'low_stock' => (int) ($summary['low_stock']['count'] ?? 0),
                'expired' => (int) ($summary['expired']['count'] ?? 0),
                'near_expiry' => (int) ($summary['near_expiry']['count'] ?? 0),
            ];

            $this->inventoryAlertCounts = $counts;

            $this->inventoryAlertCounts = $counts;

            $notificationSegments = collect([
                'Out of Stock' => $counts['out_of_stock'],
                'Low Stock' => $counts['low_stock'],
                'Expired Batches' => $counts['expired'],
                'Near Expiry' => $counts['near_expiry'],
            ])
                ->filter(fn (int $count) => $count > 0)
                ->map(fn (int $count, string $label) => "{$label}: {$count}");

            if ($notificationSegments->isNotEmpty()) {
                $this->inventoryAlertBannerMessage = $notificationSegments->implode(' • ');
            }

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
            $this->shouldShowInventoryAlertBanner = ! $this->inventoryAlertBannerDismissed && filled($this->inventoryAlertBannerMessage);

            if ($this->showInventoryAlertModal) {

                $this->dispatch('open-modal', id: 'inventory-alert-modal');
            }
        } else {
            $this->showInventoryAlertModal = false;
            $this->inventoryAlertBannerMessage = '';
            $this->inventoryAlertSummary = [];
            $this->inventoryAlertCounts = [];
            $this->shouldShowInventoryAlertBanner = false;
        }
    }

    public function dismissInventoryAlertBanner(): void
    {
        $this->inventoryAlertBannerDismissed = true;
        $this->shouldShowInventoryAlertBanner = false;

        session()->put('inventory_alert_flash_dismissed', true);
    }

    public function acknowledgeInventoryAlerts(): void
    {
        $this->inventoryAlertBannerDismissed = true;
        $this->shouldShowInventoryAlertBanner = false;
        $this->showInventoryAlertModal = false;
        $this->inventoryAlertBannerMessage = '';
        $this->inventoryAlertSummary = [];
        $this->inventoryAlertCounts = [];
        $this->inventoryAlertTotal = 0;
        $this->inventoryAlertLastCalculatedAt = null;

        session()->put('inventory_alert_flash_dismissed', true);

        $this->dispatch('close-modal', id: 'inventory-alert-modal');

        $this->redirectRoute('filament.app.pages.dashboard');
    }

    public function initializeInventoryAlertModal(): void
    {

        if (! $this->showInventoryAlertModal) {
            return;
        }

        $this->dispatch('open-modal', id: 'inventory-alert-modal');

    }

    public function getWidgets(): array
    {
        return [
            SalesPeriodSummaryWidget::class,
            DetailedSalesBreakdownWidget::class,
        ];
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'superadmin', 'manager']);
    }
}