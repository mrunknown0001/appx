<?php

namespace App\Filament\Resources\UnitResource\Pages;

use App\Filament\Resources\UnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUnits extends ListRecords
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('New Unit'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Units')
                ->badge(fn () => \App\Models\Unit::count()),
            
            'in_use' => Tab::make('In Use')
                ->modifyQueryUsing(fn (Builder $query) => $query->has('products'))
                ->badge(fn () => \App\Models\Unit::has('products')->count()),
            
            'unused' => Tab::make('Unused')
                ->modifyQueryUsing(fn (Builder $query) => $query->doesntHave('products'))
                ->badge(fn () => \App\Models\Unit::doesntHave('products')->count()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // You can add custom widgets here if needed
        ];
    }
}