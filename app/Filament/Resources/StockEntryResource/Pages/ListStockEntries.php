<?php

namespace App\Filament\Resources\StockEntryResource\Pages;

use App\Filament\Resources\StockEntryResource;
use App\Models\Product;
use App\Models\StockEntry;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStockEntries extends ListRecords
{
    protected static string $resource = StockEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('New Stock Entry'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Entries')
                ->badge(fn () => StockEntry::count()),

            'recent' => Tab::make('Recent (7 days)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('entry_date', '>=', now()->subDays(7)))
                ->badge(fn () => StockEntry::where('entry_date', '>=', now()->subDays(7))->count()),

            'high_value' => Tab::make('High Value')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('total_cost', '>', 10000))
                ->badge(fn () => StockEntry::where('total_cost', '>', 10000)->count())
                ->badgeColor('warning'),

            'multi_product' => Tab::make('Multiple Products')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('items_count', '>', 1))
                ->badge(fn () => StockEntry::where('items_count', '>', 1)->count())
                ->badgeColor('success'),

            'expires_soon' => Tab::make('Items Expiring Soon')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('items', fn (Builder $q) => $q
                    ->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [now(), now()->addDays(30)])
                ))
                ->badge(fn () => StockEntry::whereHas('items', fn (Builder $q) => $q
                    ->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [now(), now()->addDays(30)])
                )->count())
                ->badgeColor('warning'),

            'expired' => Tab::make('Expired Items')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('items', fn (Builder $q) => $q
                    ->whereNotNull('expiry_date')
                    ->where('expiry_date', '<', now())
                ))
                ->badge(fn () => StockEntry::whereHas('items', fn (Builder $q) => $q
                    ->whereNotNull('expiry_date')
                    ->where('expiry_date', '<', now())
                )->count())
                ->badgeColor('danger'),

        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Widgets may be added to surface stock entry KPIs or insights.
        ];
    }
}