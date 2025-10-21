<x-filament-panels::page class="fi-dashboard-page">
    @if ($shouldShowInventoryAlertBanner)
        @php
            logger()->info('Rendering inventory alert banner', [
                'inventory_alert_total' => $inventoryAlertTotal ?? null,
                'inventory_alert_banner_message' => $inventoryAlertBannerMessage ?? null,
                'inventory_alert_last_calculated_at' => $inventoryAlertLastCalculatedAt ?? null,
            ]);
        @endphp
        <x-filament::alert
            color="warning"
            icon="heroicon-o-exclamation-triangle"
            class="relative mb-6 pr-10"
        >
            <button
                type="button"
                class="absolute right-3 top-3 inline-flex items-center rounded-md border border-transparent bg-transparent p-1 text-sm text-warning-700 hover:text-warning-900 focus:outline-none focus:ring-2 focus:ring-warning-500 focus:ring-offset-2 dark:text-warning-400 dark:hover:text-warning-200"
                wire:click="dismissInventoryAlertBanner"
            >
                <x-heroicon-o-x-mark class="h-4 w-4" />
                <span class="sr-only">Dismiss inventory alerts</span>
            </button>

            <div class="font-semibold">
                {{ $inventoryAlertTotal }} inventory alert{{ $inventoryAlertTotal === 1 ? '' : 's' }} detected
                @if ($inventoryAlertLastCalculatedAt)
                    on {{ \Illuminate\Support\Carbon::parse($inventoryAlertLastCalculatedAt)->timezone(config('app.timezone'))->format('M d, Y g:i A') }}
                @endif
                .
            </div>

            <div class="mt-2 text-sm leading-6">
                {{ $inventoryAlertBannerMessage }}
            </div>

            @if (! empty($inventoryAlertCounts))
                <dl class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                    @foreach ([
                        'out_of_stock' => 'Out of Stock',
                        'low_stock' => 'Low Stock',
                        'expired' => 'Expired',
                        'near_expiry' => 'Near Expiry',
                    ] as $key => $label)
                        @if (($inventoryAlertCounts[$key] ?? 0) > 0)
                            <div class="rounded-lg bg-warning-100/70 px-3 py-2 text-warning-900 dark:bg-warning-500/10 dark:text-warning-200">
                                <dt class="font-semibold">{{ $label }}</dt>
                                <dd>{{ $inventoryAlertCounts[$key] }} item{{ $inventoryAlertCounts[$key] === 1 ? '' : 's' }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            @endif
        </x-filament::alert>
    @endif

    @include('filament.components.inventory-alert-modal', [
        'summary' => $inventoryAlertSummary,
        'totalAlerts' => $inventoryAlertTotal,
        'lastCalculatedAt' => $inventoryAlertLastCalculatedAt,
        'shouldShow' => $showInventoryAlertModal,
    ])

    @if (method_exists($this, 'filtersForm'))
        {{ $this->filtersForm }}
    @endif

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="
            [
                ...(property_exists($this, 'filters') ? ['filters' => $this->filters] : []),
                ...$this->getWidgetData(),
            ]
        "
        :widgets="$this->getVisibleWidgets()"
    />
</x-filament-panels::page>