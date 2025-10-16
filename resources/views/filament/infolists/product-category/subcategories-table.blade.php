<div class="overflow-x-auto w-full">
    <table class="min-w-full w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 rounded-lg shadow-sm ring-1 ring-black/5 dark:ring-white/10">
        <thead class="bg-gray-50 dark:bg-gray-900/60">
        <tr class="text-left">
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Subcategory
            </th>
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Products
            </th>
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Active
            </th>
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Created
            </th>
            <th scope="col" class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Updated
            </th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($subcategories as $subcategory)
            <tr
                class="hover:bg-gray-50/70 dark:hover:bg-gray-800/60 transition cursor-pointer"
                x-data
                @click="window.location='{{ route('filament.app.resources.product-categories.view', $subcategory) }}'"
                role="button"
                tabindex="0"
                @keydown.enter.prevent="window.location='{{ route('filament.app.resources.product-categories.view', $subcategory) }}'"
                @keydown.space.prevent="window.location='{{ route('filament.app.resources.product-categories.view', $subcategory) }}'"
            >
                <td class="px-4 py-3 align-top">
                    <div class="flex flex-col gap-1">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ $subcategory->name }}
                        </span>
                        @if ($subcategory->description)
                            <span class="text-xs leading-normal text-gray-500 dark:text-gray-400">
                                {{ $subcategory->description }}
                            </span>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">
                    {{ $subcategory->products_count ?? $subcategory->products?->count() ?? 0 }}
                </td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">
                    @if ($subcategory->is_active)
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
                    {{ $subcategory->created_at?->diffForHumans() ?? '—' }}
                </td>
                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                    {{ $subcategory->updated_at?->diffForHumans() ?? '—' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    No subcategories found for this category.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

    @if ($subcategories instanceof \Illuminate\Contracts\Pagination\Paginator && $subcategories->hasPages())
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/60 border-t border-gray-200 dark:border-gray-700 rounded-b-lg flex items-center justify-between">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Showing
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $subcategories->firstItem() }}</span>
                to
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $subcategories->lastItem() }}</span>
                of
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $subcategories->total() }}</span>
                subcategories
            </div>
            <div>
                {{ $subcategories->onEachSide(1)->links('filament::components.pagination.index') }}
            </div>
        </div>
    @endif
</div>