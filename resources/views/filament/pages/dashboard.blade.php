<x-filament-panels::page class="fi-dashboard-page">
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