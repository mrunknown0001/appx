@php
    $summary = $summary ?? [];
    $totalAlerts = $totalAlerts ?? 0;
    $lastCalculatedAt = $lastCalculatedAt ?? null;
    $shouldShow = $shouldShow ?? false;

    $hasAlerts = $shouldShow && $totalAlerts > 0 && is_array($summary) && filled($summary);

    $orderedKeys = ['out_of_stock', 'low_stock', 'expired', 'near_expiry'];

    $metaMap = [
        'out_of_stock' => [
            'title' => 'Out of Stock',
            'icon' => 'heroicon-o-x-circle',
            'colorClass' => 'text-danger-500 dark:text-danger-400',
            'label' => 'out of stock',
        ],
        'low_stock' => [
            'title' => 'Low Stock',
            'icon' => 'heroicon-o-exclamation-triangle',
            'colorClass' => 'text-warning-500 dark:text-warning-400',
            'label' => 'low stock',
        ],
        'expired' => [
            'title' => 'Expired Batches',
            'icon' => 'heroicon-o-stop-circle',
            'colorClass' => 'text-danger-500 dark:text-danger-400',
            'label' => 'expired batches',
        ],
        'near_expiry' => [
            'title' => 'Near Expiry Batches',
            'icon' => 'heroicon-o-clock',
            'colorClass' => 'text-warning-500 dark:text-warning-400',
            'label' => 'near expiry batches',
        ],
    ];
@endphp

@if ($hasAlerts)
    <div
        wire:init="initializeInventoryAlertModal"
        x-data="{
            shouldShow: @json($hasAlerts),
            hasOpened: false,
            openModal() {
                if (! this.shouldShow || this.hasOpened) {
                    return;
                }

                this.hasOpened = true;

                window.dispatchEvent(
                    new CustomEvent('open-modal', {
                        detail: { id: 'inventory-alert-modal', source: 'inventory-alert-wrapper' },
                    }),
                );
            },
            dismissModal() {
                window.dispatchEvent(
                    new CustomEvent('close-modal', {
                        detail: { id: 'inventory-alert-modal', source: 'inventory-alert-wrapper' },
                    }),
                );
            },
        }"
        x-init="openModal()"
        x-on:inventory-alert-reopen.window="openModal()"
    >
        <x-filament::modal
            id="inventory-alert-modal"
            alignment="center"
            width="5xl"
            :close-button="false"
            :close-by-clicking-away="false"
            :close-by-escaping="false"
            sticky-header
        >
    <x-slot name="heading">
        Inventory Alerts
    </x-slot>

        <x-slot name="description">
            {{ $totalAlerts }} alert{{ $totalAlerts === 1 ? '' : 's' }} detected{{ $lastCalculatedAt ? ' on ' . \Illuminate\Support\Carbon::parse($lastCalculatedAt)->timezone(config('app.timezone'))->format('M d, Y g:i A') : '' }}. Review the items below.
        </x-slot>

        <div class="space-y-6">
                @foreach ($orderedKeys as $key)
                    @php
                        $section = $summary[$key] ?? null;
                        $count = $section['count'] ?? 0;
                    @endphp

                    @continue(!($section && $count > 0))

                    @php
                        $items = array_values($section['items'] ?? []);
                        $previewItems = array_slice($items, 0, 5);
                        $previewCount = count($previewItems);
                        $showingAll = $previewCount === $count;

                        $meta = $metaMap[$key] ?? [
                            'title' => ucfirst(str_replace('_', ' ', $key)),
                            'icon' => 'heroicon-o-information-circle',
                            'colorClass' => 'text-gray-500 dark:text-gray-400',
                            'label' => strtolower(str_replace('_', ' ', $key)),
                        ];
                    @endphp

                    <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-gray-700 dark:bg-gray-900/60">
                        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700 ">
                            <div class="flex items-center gap-3">
                                <x-filament::icon :icon="$meta['icon']" :class="$meta['colorClass'] . ' h-5 w-5'" />
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $meta['title'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $section['description'] ?? 'Immediate attention recommended.' }}
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                {{ $count }} {{ $meta['label'] }}
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full w-full divide-y divide-gray-200  text-sm dark:divide-gray-800 dark:bg-gray-900">
                                <thead class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    @if (in_array($key, ['out_of_stock', 'low_stock'], true))
                                        <tr>
                                            <th class="px-4 py-2 text-left">Product</th>
                                            <th class="px-4 py-2 text-left">SKU</th>
                                            <th class="px-4 py-2 text-right">Current</th>
                                            <th class="px-4 py-2 text-right">Min Level</th>
                                            <th class="px-4 py-2 text-left">Category</th>
                                        </tr>
                                    @else
                                        <tr>
                                            <th class="px-4 py-2 text-left">Product</th>
                                            <th class="px-4 py-2 text-left">Batch</th>
                                            <th class="px-4 py-2 text-right">Quantity</th>
                                            <th class="px-4 py-2 text-left">Expiry</th>
                                            <th class="px-4 py-2 text-right">{{ $key === 'expired' ? 'Days Overdue' : 'Days Remaining' }}</th>
                                        </tr>
                                    @endif
                                </thead>
                                <tbody class="divide-y divide-gray-200  dark:divide-gray-800 dark:bg-gray-900/40">
                                    @foreach ($previewItems as $item)
                                        @if (in_array($key, ['out_of_stock', 'low_stock'], true))
                                            <tr class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900/60 dark:even:bg-gray-900/40">
                                                <td class="px-4 py-2 font-medium text-gray-600 dark:text-gray-400">{{ $item['name'] ?? 'Unknown' }}</td>
                                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $item['sku'] ?? '—' }}</td>
                                                <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($item['current_stock'] ?? 0) }}</td>
                                                <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ number_format($item['min_stock_level'] ?? 0) }}</td>
                                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $item['category'] ?? '—' }}</td>
                                            </tr>
                                        @else
                                            @php
                                                $expiry = $item['expiry_date'] ?? null;
                                                $qty = $item['quantity'] ?? 0;
                                                $metric = $key === 'expired'
                                                    ? ($item['days_overdue'] ?? 0)
                                                    : ($item['days_remaining'] ?? 0);
                                            @endphp
                                            <tr class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900/60 dark:even:bg-gray-900/40">
                                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $item['product_name'] ?? 'Unknown' }}</td>
                                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $item['batch_number'] ?? '—' }}</td>
                                                <td class="px-4 py-2 text-right text-gray-900 dark:text-gray-100">{{ number_format($qty) }}</td>
                                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                                    {{ $expiry ? \Illuminate\Support\Carbon::parse($expiry)->format('M d, Y') : '—' }}
                                                </td>
                                                <td class="px-4 py-2 text-right text-gray-900 dark:text-gray-100">{{ $metric }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @unless ($showingAll)
                            <div class="border-t border-gray-200 bg-gray-50 px-4 py-2 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900/70 dark:text-gray-400">
                                Showing {{ $previewCount }} of {{ $count }} entries.
                            </div>
                        @endunless
                    </div>
                @endforeach
            </div>

        <x-slot name="footer">
            <div class="flex w-full flex-wrap items-center justify-between gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span>Address these alerts through the inventory and batch listings.</span>
                <div class="flex items-center gap-2">
                    <x-filament::button
                        tag="a"
                        color="gray"
                        outlined
                        href="{{ route('filament.app.resources.products.index') }}"
                    >
                        Manage Products
                    </x-filament::button>

                    <x-filament::button
                        color="primary"
                        type="button"
                        x-on:click.prevent="dismissModal(); $wire.acknowledgeInventoryAlerts()"
                    >
                        Close Alerts
                    </x-filament::button>
                </div>
            </div>
        </x-slot>
        </x-filament::modal>
    </div>
@endif