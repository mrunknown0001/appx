<?php

namespace App\Filament\Resources\StockEntryResource\Pages;

use App\Filament\Resources\StockEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\StockEntry;

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
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('entry_date', '>=', now()->subDays(7))
                )
                ->badge(fn () => StockEntry::where('entry_date', '>=', now()->subDays(7))->count()),
            
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('expiry_date', '<', now())
                )
                ->badge(fn () => StockEntry::where('expiry_date', '<', now())->count())
                ->badgeColor('danger'),
            
            'expires_soon' => Tab::make('Expires Soon')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('expiry_date', '>=', now())
                          ->where('expiry_date', '<=', now()->addDays(30))
                )
                ->badge(fn () => StockEntry::where('expiry_date', '>=', now())
                    ->where('expiry_date', '<=', now()->addDays(30))->count())
                ->badgeColor('warning'),
            
            'this_month' => Tab::make('This Month')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereMonth('entry_date', now()->month)
                          ->whereYear('entry_date', now()->year)
                )
                ->badge(fn () => StockEntry::whereMonth('entry_date', now()->month)
                    ->whereYear('entry_date', now()->year)->count()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // You can add widgets here for stock entry statistics
        ];
    }
}