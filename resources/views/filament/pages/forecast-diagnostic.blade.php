<x-filament-panels::page>
    <div class="space-y-6">
        @if($diagnosticData)
            {{-- Overall Statistics --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Sales</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
                        {{ number_format($diagnosticData['total_sales']) }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Sale Items</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
                        {{ number_format($diagnosticData['total_sale_items']) }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Products</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
                        {{ number_format($diagnosticData['total_products']) }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Products Ready</div>
                    <div class="text-3xl font-bold mt-2 {{ $diagnosticData['products_ready_for_forecast']['count'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ number_format($diagnosticData['products_ready_for_forecast']['count']) }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Ready for forecasting</div>
                </div>
            </div>

            {{-- Data Range --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Data Range</h2>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Oldest Sale</div>
                        <div class="text-lg font-medium text-gray-900 dark:text-white">
                            {{ $diagnosticData['date_range']['oldest'] }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Newest Sale</div>
                        <div class="text-lg font-medium text-gray-900 dark:text-white">
                            {{ $diagnosticData['date_range']['newest'] }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Days of Data</div>
                        <div class="text-lg font-medium text-gray-900 dark:text-white">
                            {{ number_format($diagnosticData['date_range']['days']) }} days
                        </div>
                    </div>
                </div>
            </div>

            {{-- Forecast Readiness Alert --}}
            @if($diagnosticData['products_ready_for_forecast']['count'] === 0)
                <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-6 rounded-lg">
                    <div class="flex items-start">
                        {{-- <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-500 mr-3 flex-shrink-0"/> --}}
                        <div>
                            <h3 class="text-lg font-semibold text-red-800 dark:text-red-200">
                                No Products Ready for Forecasting
                            </h3>
                            <p class="text-red-700 dark:text-red-300 mt-2">
                                You need at least 3 months of sales data per product to generate forecasts. 
                            </p>
                            <div class="mt-4">
                                <p class="text-red-700 dark:text-red-300 font-semibold">Solutions:</p>
                                <ul class="list-disc list-inside text-red-700 dark:text-red-300 mt-2 space-y-1">
                                    <li>Run the sample data seeder: <code class="bg-red-200 dark:bg-red-800 px-2 py-1 rounded">php artisan db:seed --class=SampleSalesDataSeeder</code></li>
                                    <li>Wait for real sales data to accumulate (minimum 3 months)</li>
                                    <li>Manually create historical sales records</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-6 rounded-lg">
                    <div class="flex items-start">
                        {{-- <x-heroicon-o-check-circle class="w-6 h-6 text-green-500 mr-3 flex-shrink-0"/> --}}
                        <div>
                            <h3 class="text-lg font-semibold text-green-800 dark:text-green-200">
                                System Ready for Forecasting
                            </h3>
                            <p class="text-green-700 dark:text-green-300 mt-2">
                                {{ $diagnosticData['products_ready_for_forecast']['count'] }} product(s) have sufficient data for forecasting. 
                                You can proceed to the Sales & Inventory Forecast page.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Monthly Sales Summary --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Monthly Sales Summary</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Month</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Sales Count</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Quantity</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($diagnosticData['monthly_sales_count'] as $month)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $month->month }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format($month->count) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format($month->total_quantity) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">₱{{ number_format($month->total_revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No sales data found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Products with Sales Data --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Products with Sales Data</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Product</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Months of Data</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Sales</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Quantity</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Revenue</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($diagnosticData['products_with_data'] as $product)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $product->name }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">{{ $product->months }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format($product->total_sales) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format($product->total_quantity) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">₱{{ number_format($product->total_revenue, 2) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($product->months >= 3)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                ✓ Ready
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                Need {{ 3 - $product->months }} more
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No products with sales data</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Refresh Button --}}
            <div class="flex justify-center">
                <x-filament::button wire:click="runDiagnostic">
                    {{-- <x-heroicon-o-arrow-path class="w-5 h-5 mr-2"/> --}}
                    Refresh Diagnostic
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>