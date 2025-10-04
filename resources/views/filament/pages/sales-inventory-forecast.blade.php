<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filter Form --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <form wire:submit="generateForecast">
                {{ $this->form }}
                
                <div class="mt-6">
                    <x-filament::button type="submit" size="lg">
                        {{-- <x-heroicon-o-chart-bar class="w-5 h-5 mr-2"/> --}}
                        Generate Forecast
                    </x-filament::button>
                </div>
            </form>
        </div>

        @if($forecastData)
            {{-- Sales Forecast Section --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
                        {{-- <x-heroicon-o-chart-line class="w-6 h-6 mr-2 text-primary-600"/> --}}
                        Sales Forecast (ARIMA Model)
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Predictive analysis using ARIMA(1,1,1) time series forecasting
                    </p>
                </div>

                <div class="p-6 space-y-8">
                    @foreach($forecastData as $productId => $forecast)
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-8 last:border-0">
                            {{-- Product Header --}}
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ $forecast['product']->name }}
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Category: {{ $forecast['product']->category->name ?? 'N/A' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Confidence Interval (95%)</div>
                                    <div class="text-xs text-gray-500">
                                        ± {{ number_format($forecast['confidence_interval']['std_dev'], 2) }} units
                                    </div>
                                </div>
                            </div>

                            {{-- Forecast Chart --}}
                            <div class="grid md:grid-cols-2 gap-6">
                                {{-- Quantity Forecast --}}
                                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                        Quantity Forecast
                                    </h4>
                                    <div class="h-64" 
                                         x-data="quantityChart(@js($forecast['historical_quantities']), @js($forecast['forecasted_quantities']), @js($forecast['forecast_dates']))"
                                         x-init="initChart()">
                                        <canvas x-ref="chart"></canvas>
                                    </div>
                                </div>

                                {{-- Revenue Forecast --}}
                                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                        Revenue Forecast
                                    </h4>
                                    <div class="h-64" 
                                         x-data="revenueChart(@js($forecast['historical_revenues']), @js($forecast['forecasted_revenues']), @js($forecast['forecast_dates']))"
                                         x-init="initChart()">
                                        <canvas x-ref="chart"></canvas>
                                    </div>
                                </div>
                            </div>

                            {{-- Forecast Summary Table --}}
                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-100 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Period</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Forecasted Quantity</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Forecasted Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($forecast['forecast_dates'] as $index => $date)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $date }}</td>
                                                <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">
                                                    {{ number_format($forecast['forecasted_quantities'][$index], 2) }} units
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">
                                                    ₱{{ number_format($forecast['forecasted_revenues'][$index], 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-gray-100 dark:bg-gray-800">
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">Total</td>
                                            <td class="px-4 py-3 text-sm font-semibold text-right text-gray-900 dark:text-white">
                                                {{ number_format(array_sum($forecast['forecasted_quantities']), 2) }} units
                                            </td>
                                            <td class="px-4 py-3 text-sm font-semibold text-right text-gray-900 dark:text-white">
                                                ₱{{ number_format(array_sum($forecast['forecasted_revenues']), 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Restock Recommendations Section --}}
            @if($restockData)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
                            {{-- <x-heroicon-o-arrow-path class="w-6 h-6 mr-2 text-success-600"/> --}}
                            Inventory Restock Recommendations
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            AI-powered restock suggestions based on forecasted demand
                        </p>
                    </div>

                    <div class="p-6">
                        <div class="grid gap-6">
                            @foreach($restockData as $productId => $restock)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                                    {{-- Product Info --}}
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                {{ $restock['product']->name }}
                                            </h3>
                                            <div class="flex items-center gap-4 mt-2 text-sm">
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Current Stock: <strong class="text-gray-900 dark:text-white">{{ number_format($restock['current_stock']) }}</strong>
                                                </span>
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Min Level: <strong class="text-gray-900 dark:text-white">{{ number_format($restock['min_stock_level']) }}</strong>
                                                </span>
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Avg Monthly Demand: <strong class="text-gray-900 dark:text-white">{{ number_format($restock['average_monthly_demand']) }}</strong>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold text-primary-600">
                                                {{ number_format($restock['total_restock_needed']) }}
                                            </div>
                                            <div class="text-xs text-gray-500">Total Units Needed</div>
                                        </div>
                                    </div>

                                    {{-- Restock Points --}}
                                    @if(count($restock['restock_points']) > 0)
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-900">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Period</th>
                                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recommended Qty</th>
                                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Projected Stock</th>
                                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Urgency</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($restock['restock_points'] as $point)
                                                        <tr>
                                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $point['period'] }}</td>
                                                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">
                                                                {{ number_format($point['recommended_quantity']) }}
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">
                                                                {{ number_format($point['current_stock_projection']) }}
                                                            </td>
                                                            <td class="px-4 py-3 text-center">
                                                                @php
                                                                    $urgencyColors = [
                                                                        'CRITICAL' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                                        'HIGH' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                                                        'MEDIUM' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                                        'LOW' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                                    ];
                                                                @endphp
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $urgencyColors[$point['urgency']] }}">
                                                                    {{ $point['urgency'] }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                                            <div class="flex items-center">
                                                {{-- <x-heroicon-o-check-circle class="w-5 h-5 text-green-600 dark:text-green-400 mr-2"/> --}}
                                                <span class="text-sm text-green-800 dark:text-green-200">
                                                    Stock levels are sufficient for the forecasted period
                                                </span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        function quantityChart(historical, forecasted, dates) {
            return {
                chart: null,
                initChart() {
                    const ctx = this.$refs.chart;
                    this.chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: dates,
                            datasets: [{
                                label: 'Forecasted Quantity',
                                data: forecasted,
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            }
        }

        function revenueChart(historical, forecasted, dates) {
            return {
                chart: null,
                initChart() {
                    const ctx = this.$refs.chart;
                    this.chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: dates,
                            datasets: [{
                                label: 'Forecasted Revenue',
                                data: forecasted,
                                borderColor: 'rgb(16, 185, 129)',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return '₱' + context.parsed.y.toFixed(2);
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '₱' + value.toFixed(0);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }
        }
    </script>
    @endpush
</x-filament-panels::page>