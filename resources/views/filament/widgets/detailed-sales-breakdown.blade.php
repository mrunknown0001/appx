@php
    $data = $this->getViewData();
    $widgetId = 'revenue-chart-' . md5($this->getId());
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        {{-- Header with Period Selector --}}
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ $data['title'] }}
            </h3>
            
            <div class="flex space-x-2">
                <button 
                    wire:click="setPeriod('week')"
                    class="px-3 py-1 rounded-lg text-sm font-medium transition-colors
                           {{ $selectedPeriod === 'week' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    Week
                </button>
                <button 
                    wire:click="setPeriod('month')"
                    class="px-3 py-1 rounded-lg text-sm font-medium transition-colors
                           {{ $selectedPeriod === 'month' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    Month
                </button>
                <button 
                    wire:click="setPeriod('year')"
                    class="px-3 py-1 rounded-lg text-sm font-medium transition-colors
                           {{ $selectedPeriod === 'year' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    Year
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            {{-- Key Metrics --}}
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">Key Metrics</h4>
                    
                    <div class="space-y-4">
                        {{-- Revenue --}}
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Revenue</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    ₱{{ number_format($data['period_data']['current']['revenue'], 2) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                           {{ $data['comparison_data']['revenue_change']['direction'] === 'up' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                              ($data['comparison_data']['revenue_change']['direction'] === 'down' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200') }}">
                                    @if($data['comparison_data']['revenue_change']['direction'] === 'up')
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    @elseif($data['comparison_data']['revenue_change']['direction'] === 'down')
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    @endif
                                    {{ $data['comparison_data']['revenue_change']['formatted'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Transactions --}}
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Transactions</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format($data['period_data']['current']['transactions']) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                           {{ $data['comparison_data']['transaction_change']['direction'] === 'up' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                              ($data['comparison_data']['transaction_change']['direction'] === 'down' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200') }}">
                                    {{ $data['comparison_data']['transaction_change']['formatted'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Average Transaction --}}
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Avg Transaction</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    ₱{{ number_format($data['period_data']['current']['avg_transaction'], 2) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                           {{ $data['comparison_data']['avg_transaction_change']['direction'] === 'up' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                              ($data['comparison_data']['avg_transaction_change']['direction'] === 'down' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200') }}">
                                    {{ $data['comparison_data']['avg_transaction_change']['formatted'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Items Sold --}}
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Items Sold</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format($data['period_data']['current']['items_sold']) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                           {{ $data['comparison_data']['items_change']['direction'] === 'up' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                              ($data['comparison_data']['items_change']['direction'] === 'down' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200') }}">
                                    {{ $data['comparison_data']['items_change']['formatted'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Chart --}}
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">Revenue Trend</h4>
                    
                    <div class="h-64">
                        <canvas id="{{ $widgetId }}" class="w-full h-full"></canvas>
                    </div>
                </div>
            </div>

            {{-- Insights --}}
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">Insights</h4>
                    
                    <div class="space-y-3">
                        @forelse($data['insights'] as $insight)
                            <div class="flex items-start space-x-3 p-3 rounded-lg
                                       {{ $insight['type'] === 'success' ? 'bg-green-50 border border-green-200 dark:bg-green-900/20 dark:border-green-800' : 
                                          ($insight['type'] === 'warning' ? 'bg-yellow-50 border border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-800' : 'bg-blue-50 border border-blue-200 dark:bg-blue-900/20 dark:border-blue-800') }}">
                                <div class="flex-shrink-0">
                                    <svg class="w-5 h-5 
                                               {{ $insight['type'] === 'success' ? 'text-green-600 dark:text-green-400' : 
                                                  ($insight['type'] === 'warning' ? 'text-yellow-600 dark:text-yellow-400' : 'text-blue-600 dark:text-blue-400') }}" 
                                         fill="currentColor" viewBox="0 0 20 20">
                                        @if($insight['type'] === 'success')
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        @elseif($insight['type'] === 'warning')
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        @else
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        @endif
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium 
                                             {{ $insight['type'] === 'success' ? 'text-green-800 dark:text-green-200' : 
                                                ($insight['type'] === 'warning' ? 'text-yellow-800 dark:text-yellow-200' : 'text-blue-800 dark:text-blue-200') }}">
                                        {{ $insight['title'] }}
                                    </p>
                                    <p class="text-sm 
                                             {{ $insight['type'] === 'success' ? 'text-green-700 dark:text-green-300' : 
                                                ($insight['type'] === 'warning' ? 'text-yellow-700 dark:text-yellow-300' : 'text-blue-700 dark:text-blue-300') }}">
                                        {{ $insight['message'] }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">No insights available for this period.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Quarterly Data (Year view only) --}}
        @if($selectedPeriod === 'year' && !empty($data['quarterly_data']))
            <div class="mt-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">Quarterly Breakdown</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        @foreach($data['quarterly_data'] as $quarter)
                            <div class="text-center p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                                <h5 class="font-medium text-gray-900 dark:text-gray-100">{{ $quarter['name'] }}</h5>
                                <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1">
                                    ₱{{ number_format($quarter['revenue'], 0) }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ number_format($quarter['transactions']) }} transactions
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

@script
<script>
    console.log('Script is loading...');
    
    // Chart data
    const chartData = @json([
        'labels' => $data['chart_data']['labels'] ?? [],
        'revenue' => $data['chart_data']['revenue'] ?? []
    ]);
    
    const canvasId = '{{ $widgetId }}';
    
    console.log('Canvas ID:', canvasId);
    console.log('Chart data:', chartData);
    
    let chartInstance = null;
    
    function loadChart() {
        console.log('loadChart called');
        
        // Check if Chart.js is loaded
        if (typeof Chart === 'undefined') {
            console.log('Chart.js not loaded, loading now...');
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
            script.onload = function() {
                console.log('Chart.js loaded successfully');
                createChart();
            };
            script.onerror = function() {
                console.error('Failed to load Chart.js');
            };
            document.head.appendChild(script);
        } else {
            console.log('Chart.js already available');
            createChart();
        }
    }
    
    function createChart() {
        console.log('createChart called');
        
        const canvas = document.getElementById(canvasId);
        console.log('Canvas element:', canvas);
        
        if (!canvas) {
            console.error('Canvas not found with ID:', canvasId);
            return;
        }
        
        // Destroy existing chart
        if (chartInstance) {
            console.log('Destroying old chart');
            chartInstance.destroy();
        }
        
        try {
            chartInstance = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Revenue',
                        data: chartData.revenue,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Revenue: ₱' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            console.log('Chart created successfully!', chartInstance);
        } catch (error) {
            console.error('Error creating chart:', error);
        }
    }
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadChart);
    } else {
        loadChart();
    }
    
    console.log('Script setup complete');
</script>
@endscript