<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ListProductCategories extends ListRecords
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            // 'all' => Tab::make('All Categories')
            //     ->badge(fn () => \App\Models\ProductCategory::count()),
            
            'main' => Tab::make('Main Categories')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('parent_id'))
                ->badge(fn () => \App\Models\ProductCategory::whereNull('parent_id')->count()),
            
            'sub' => Tab::make('Subcategories')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('parent_id'))
                ->badge(fn () => \App\Models\ProductCategory::whereNotNull('parent_id')->count()),
            
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true))
                ->badge(fn () => \App\Models\ProductCategory::where('is_active', true)->count()),
            
            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false))
                ->badge(fn () => \App\Models\ProductCategory::where('is_active', false)->count())
                ->badgeColor('warning'),
        ];
    }

    protected function getTableRecordUrl(?Model $record): ?string
    {
        if (! $record) {
            return null;
        }

        return ProductCategoryResource::getUrl('view', ['record' => $record]);
    }
}