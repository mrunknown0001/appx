<div class="space-y-4">
    @if($saleItems->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Products by Quantity Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    Products by Quantity
                </h3>
                <div class="space-y-3">
                    @foreach($saleItems->sortByDesc('quantity') as $item)
                        @php
                            $maxQuantity = $saleItems->max('quantity');
                            $percentage = $maxQuantity > 0 ? ($item->quantity / $maxQuantity) * 100 : 0;
                        @endphp
                        <div class="space-y-1">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">
                                    {{ $item->product->name }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ number_format($item->quantity) }} {{ $item->product->unit->abbreviation ?? 'units' }}
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-blue-600 dark:bg-blue-500 h-2 rounded-full transition-all duration-300" 
                                     style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Products by Revenue Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    Products by Revenue
                </h3>
                <div class="space-y-3">
                    @foreach($saleItems->sortByDesc('total_price') as $item)
                        @php
                            $maxRevenue = $saleItems->max('total_price');
                            $percentage = $maxRevenue > 0 ? ($item->total_price / $maxRevenue) * 100 : 0;
                        @endphp
                        <div class="space-y-1">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">
                                    {{ $item->product->name }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    ₱{{ number_format($item->total_price, 2) }}
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-green-600 dark:bg-green-500 h-2 rounded-full transition-all duration-300" 
                                     style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Product Categories Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Categories Summary
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @php
                    $categoriesData = $saleItems->groupBy('product.category.name')->map(function ($items, $category) {
                        return [
                            'name' => $category ?: 'Uncategorized',
                            'quantity' => $items->sum('quantity'),
                            'revenue' => $items->sum('total_price'),
                            'count' => $items->count(),
                        ];
                    });
                @endphp
                
                @foreach($categoriesData as $category)
                    <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            {{ $category['count'] }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                            {{ $category['name'] }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            {{ number_format($category['quantity']) }} units
                        </div>
                        <div class="text-sm font-medium text-green-600 dark:text-green-400">
                            ₱{{ number_format($category['revenue'], 2) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Batch Information -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Inventory Batches Used
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-2 text-gray-700 dark:text-gray-300">Batch Number</th>
                            <th class="text-left py-2 text-gray-700 dark:text-gray-300">Product</th>
                            <th class="text-right py-2 text-gray-700 dark:text-gray-300">Quantity Used</th>
                            <th class="text-right py-2 text-gray-700 dark:text-gray-300">Expiry Date</th>
                            <th class="text-right py-2 text-gray-700 dark:text-gray-300">Remaining Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($saleItems as $item)
                            <tr class="border-b border-gray-100 dark:border-gray-600">
                                <td class="py-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-gray-200">
                                        {{ $item->inventoryBatch->batch_number }}
                                    </span>
                                </td>
                                <td class="py-2 text-gray-900 dark:text-gray-100">
                                    {{ $item->product->name }}
                                </td>
                                <td class="py-2 text-right text-gray-600 dark:text-gray-300">
                                    {{ number_format($item->quantity) }} {{ $item->product->unit->abbreviation ?? 'units' }}
                                </td>
                                <td class="py-2 text-right">
                                    @php
                                        $expiryDate = $item->inventoryBatch->expiry_date;
                                        $isExpired = $expiryDate->isPast();
                                        $isNearExpiry = $expiryDate->diffInDays() <= 30;
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        {{ $isExpired ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                           ($isNearExpiry ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200') }}">
                                        {{ $expiryDate->format('M d, Y') }}
                                    </span>
                                </td>
                                <td class="py-2 text-right">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        {{ $item->inventoryBatch->current_quantity > 0 ? 
                                           'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                           'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200' }}">
                                        {{ number_format($item->inventoryBatch->current_quantity) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="text-center py-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-8V4a1 1 0 00-1-1H6a1 1 0 00-1 1v1m16 0V4a1 1 0 00-1-1H6a1 1 0 00-1 1v1"></path>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No Sale Items</h3>
            <p class="text-gray-500 dark:text-gray-400">This sale doesn't have any items yet.</p>
        </div>
    @endif
</div>