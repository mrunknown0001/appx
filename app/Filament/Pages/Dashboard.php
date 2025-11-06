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
        Log::info('Filament dashboard mount invoked', [
            'user_id' => optional(auth()->user())->getAuthIdentifier(),
            'is_authenticated' => auth()->check(),
        ]);

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

        Log::info('Inventory alert dashboard mount pulled session data', [
            'summary_is_array' => is_array($summary),
            'summary_keys' => is_array($summary) ? array_keys($summary) : null,
            'summary_counts' => is_array($summary) ? collect($summary)->map(fn ($section) => $section['count'] ?? null)->all() : null,
            'total_alerts' => $total,
            'timestamp_present' => ! empty($timestamp),
        ]);

        $themeCookie = request()->cookie('filament_app_theme');
        $themeSession = session('filament_app_theme');
        $prefersDarkTheme = in_array('dark', [$themeCookie, $themeSession], true);

        Log::info('Inventory alert modal theme context captured', [
            'theme_cookie' => $themeCookie,
            'theme_session' => $themeSession,
            'interpreted_prefers_dark' => $prefersDarkTheme,
        ]);

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

                Log::info('Inventory alert banner prepared', [
                    'total_alerts' => $total,
                    'segments' => $notificationSegments->values()->all(),
                ]);
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

            Log::info('Inventory alert modal will be shown on dashboard', [
                'total_alerts' => $this->inventoryAlertTotal,
                'timestamp' => $this->inventoryAlertLastCalculatedAt,
                'banner_message' => $this->inventoryAlertBannerMessage,
                'show_modal_flag' => $this->showInventoryAlertModal,
            ]);

            if ($this->showInventoryAlertModal) {
                Log::info('Inventory alert modal dispatching open-modal event', [
                    'event' => 'open-modal',
                    'modal_id' => 'inventory-alert-modal',
                ]);

                $this->dispatch('open-modal', id: 'inventory-alert-modal');
            }
        } else {
            $this->showInventoryAlertModal = false;
            $this->inventoryAlertBannerMessage = '';
            $this->inventoryAlertSummary = [];
            $this->inventoryAlertCounts = [];
            $this->shouldShowInventoryAlertBanner = false;

            Log::info('Inventory alert modal skipped on dashboard', [
                'total_alerts' => $total,
                'summary_empty' => empty($summary),
            ]);
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

        Log::info('Inventory alert modal dispatching close-modal event', [
            'event' => 'close-modal',
            'modal_id' => 'inventory-alert-modal',
        ]);

        $this->dispatch('close-modal', id: 'inventory-alert-modal');

        Log::info('Inventory alert modal dismissed by user', [
            'total_alerts' => $this->inventoryAlertTotal,
        ]);

        $this->redirectRoute('filament.app.pages.dashboard');
    }

    public function initializeInventoryAlertModal(): void
    {
        Log::info('Inventory alert modal wire:init invoked', [
            'show_modal_flag' => $this->showInventoryAlertModal,
            'total_alerts' => $this->inventoryAlertTotal,
        ]);

        if (! $this->showInventoryAlertModal) {
            return;
        }

        $this->dispatch('open-modal', id: 'inventory-alert-modal');

        Log::info('Inventory alert modal open-modal dispatched from wire:init', [
            'event' => 'open-modal',
            'modal_id' => 'inventory-alert-modal',
        ]);
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
        return in_array(auth()->user()->role, ['admin', 'superadmin']);
    }
}