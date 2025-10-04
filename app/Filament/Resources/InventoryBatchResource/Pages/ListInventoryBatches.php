<?php

namespace App\Filament\Resources\InventoryBatchResource\Pages;

use App\Filament\Resources\InventoryBatchResource;
use App\Models\InventoryBatch;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInventoryBatches extends ListRecords
{
    protected static string $resource = InventoryBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('New Inventory Batch'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Batches')
                ->badge(fn () => InventoryBatch::count()),
            
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge(fn () => InventoryBatch::where('status', 'active')->count()),
            
            'low_stock' => Tab::make('Low Stock')
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->whereHas('product', function ($q) {
                        $q->whereRaw('inventory_batches.current_quantity <= products.min_stock_level');
                    })->where('status', 'active');
                })
                ->badge(fn () => $this->getLowStockCount())
                ->badgeColor('warning'),
            
            'out_of_stock' => Tab::make('Out of Stock')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('current_quantity', '<=', 0)->where('status', 'out_of_stock')
                )
                ->badge(fn () => InventoryBatch::where('current_quantity', '<=', 0)
                    ->where('status', 'out_of_stock')->count())
                ->badgeColor('danger'),
            
            'expiring_soon' => Tab::make('Expiring Soon')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('expiry_date', '<=', now()->addDays(30))
                          ->where('expiry_date', '>', now())
                          ->where('status', 'active')
                )
                ->badge(fn () => $this->getExpiringSoonCount())
                ->badgeColor('warning'),
            
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('expiry_date', '<', now())->orWhere('status', 'expired')
                )
                ->badge(fn () => $this->getExpiredCount())
                ->badgeColor('danger'),
            
            // 'depleted' => Tab::make('Depleted')
            //     ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'depleted'))
            //     ->badge(fn () => InventoryBatch::where('status', 'depleted')->count())
            //     ->badgeColor('gray'),
        ];
    }

    protected function getLowStockCount(): int
    {
        return InventoryBatch::whereHas('product', function ($query) {
            $query->whereRaw('inventory_batches.current_quantity <= products.min_stock_level');
        })->where('status', 'active')->count();
    }

    protected function getExpiringSoonCount(): int
    {
        return InventoryBatch::where('expiry_date', '<=', now()->addDays(30))
            ->where('expiry_date', '>', now())
            ->where('status', 'active')
            ->count();
    }

    protected function getExpiredCount(): int
    {
        return InventoryBatch::where('expiry_date', '<', now())
            ->orWhere('status', 'expired')
            ->count();
    }
}