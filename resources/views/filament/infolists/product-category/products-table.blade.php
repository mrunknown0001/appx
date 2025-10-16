<div class="overflow-x-auto w-full">
    <table class="min-w-full w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 rounded-lg shadow-sm ring-1 ring-black/5 dark:ring-white/10">
        <thead class="bg-gray-50 dark:bg-gray-900/60">
        <tr class="text-left">
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Product
            </th>
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                SKU
            </th>
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Unit
            </th>
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Stock
            </th>
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Price
            </th>
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Active
            </th>
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Updated
            </th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($products as $product)
            <tr
                class="hover:bg-gray-50/70 dark:hover:bg-gray-800/60 transition cursor-pointer"
                x-data
                @click="window.location='{{ route('filament.app.resources.products.view', $product) }}'"
                role="button"
                tabindex="0"
                @keydown.enter.prevent="window.location='{{ route('filament.app.resources.products.view', $product) }}'"
                @keydown.space.prevent="window.location='{{ route('filament.app.resources.products.view', $product) }}'"
            >
                <td class="px-4 py-3 align-top">
                    <div class="flex flex-col gap-1">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ $product->name }}
                        </span>
                        @if ($product->generic_name || $product->manufacturer)
                            <span class="text-xs leading-normal text-gray-500 dark:text-gray-400">
                                {{ $product->generic_name ?? 'N/A' }} @if ($product->manufacturer) • {{ $product->manufacturer }} @endif
                            </span>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">
                    <span class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-500/20 px-2 py-1 text-xs font-mono font-medium text-gray-800 dark:text-gray-100">
                        {{ $product->sku }}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">
                    {{ $product->unit?->name }} @if ($product->unit?->abbreviation) ({{ $product->unit->abbreviation }}) @endif
                </td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">
                    @php
                        $stock = $product->getCurrentStock();
                    @endphp
                    <span @class([
                        'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium',
                        'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-200' => $stock > $product->min_stock_level,
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/20 dark:text-yellow-200' => $stock > 0 && $stock <= $product->min_stock_level,
                        'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-200' => $stock <= 0,
                    ])>
                        {{ number_format($stock) }}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">
                    ₱{{ number_format($product->getCurrentPrice(), 2) }}
                </td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">
                    @if ($product->is_active)
                        <span class="inline-flex items-center rounded-md bg-green-100 dark:bg-green-500/20 px-2 py-1 text-xs font-semibold text-green-800 dark:text-green-200">
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-md bg-red-100 dark:bg-red-500/20 px-2 py-1 text-xs font-semibold text-red-800 dark:text-red-200">
                            Inactive
                        </span>
                    @endif
                </td>
                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                    {{ $product->updated_at?->diffForHumans() ?? '—' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    No products are currently assigned to this category.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

    @if ($products instanceof \Illuminate\Contracts\Pagination\Paginator && $products->hasPages())
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/60 border-t border-gray-200 dark:border-gray-700 rounded-b-lg flex items-center justify-between">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Showing
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $products->firstItem() }}</span>
                to
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $products->lastItem() }}</span>
                of
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $products->total() }}</span>
                products
            </div>
            <div>
                {{ $products->onEachSide(1)->links('filament::components.pagination.index') }}
            </div>
        </div>
    @endif
</div>