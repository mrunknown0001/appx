<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Product;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('New Product'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Products')
                ->badge(fn () => Product::count()),
            
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('products.is_active', true))
                ->badge(fn () => Product::where('is_active', true)->count()),
            
            'low_stock' => Tab::make('Low Stock')
                ->modifyQueryUsing(function (Builder $query) {
                    return $query
                        ->leftJoin('inventory_batches', function($join) {
                            $join->on('products.id', '=', 'inventory_batches.product_id')
                                 ->where('inventory_batches.expiry_date', '>', now())
                                 ->where('inventory_batches.status', '=', 'active');
                        })
                        ->selectRaw('products.*, COALESCE(SUM(inventory_batches.current_quantity), 0) as total_stock')
                        ->groupBy('products.id')
                        ->havingRaw('total_stock <= products.min_stock_level');
                })
                ->badge(fn () => $this->getLowStockCount())
                ->badgeColor('warning'),
            
            'out_of_stock' => Tab::make('Out of Stock')
                ->modifyQueryUsing(function (Builder $query) {
                    return $query
                        ->leftJoin('inventory_batches', function($join) {
                            $join->on('products.id', '=', 'inventory_batches.product_id')
                                 ->where('inventory_batches.expiry_date', '>', now())
                                 ->where('inventory_batches.status', '=', 'active');
                        })
                        ->selectRaw('products.*, COALESCE(SUM(inventory_batches.current_quantity), 0) as total_stock')
                        ->groupBy('products.id')
                        ->havingRaw('total_stock = 0');
                })
                ->badge(fn () => $this->getOutOfStockCount())
                ->badgeColor('danger'),
        ];
    }

    private function getLowStockCount(): int
    {
        return \DB::table('product_stocks')
            ->where('stock_status', 'low_stock')
            ->count();
    }

    private function getOutOfStockCount(): int
    {
        return Product::leftJoin('inventory_batches', function($join) {
                $join->on('products.id', '=', 'inventory_batches.product_id')
                     ->where('inventory_batches.expiry_date', '>', now())
                     ->where('inventory_batches.status', '=', 'active');
            })
            ->selectRaw('products.id, COALESCE(SUM(inventory_batches.current_quantity), 0) as total_stock')
            ->groupBy('products.id')
            ->havingRaw('total_stock = 0')
            ->count();
    }
}