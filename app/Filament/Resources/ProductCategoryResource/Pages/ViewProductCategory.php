<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Support\Enums\FontWeight;

class ViewProductCategory extends ViewRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Category Details')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('name')
                                ->label('Category Name')
                                ->weight(FontWeight::Bold)
                                ->size('lg'),

                            TextEntry::make('parent.name')
                                ->label('Parent Category')
                                ->placeholder('Main Category'),

                            IconEntry::make('is_active')
                                ->label('Active')
                                ->boolean()
                                ->trueIcon('heroicon-o-check-circle')
                                ->falseIcon('heroicon-o-x-circle')
                                ->trueColor('success')
                                ->falseColor('danger'),

                            TextEntry::make('children_count')
                                ->label('Subcategories')
                                ->getStateUsing(fn ($record): int => $record->children->count())
                                ->badge(),

                            TextEntry::make('products_count')
                                ->label('Products')
                                ->getStateUsing(fn ($record): int => $record->products->count())
                                ->badge(),

                            TextEntry::make('created_at')
                                ->label('Created At')
                                ->dateTime()
                                ->color('gray'),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime()
                                ->color('gray'),
                        ]),
                    TextEntry::make('description')
                        ->label('Description')
                        ->placeholder('No description provided.')
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull(),

            Section::make('Subcategories')
                ->schema([
                    ViewEntry::make('subcategories_table')
                        ->label(false)
                        ->view('filament.infolists.product-category.subcategories-table')
                        ->viewData(fn ($record) => [
                            'subcategories' => $record->children()
                                ->withCount('products')
                                ->orderBy('name')
                                ->paginate(5, ['*'], 'subcategoriesPage')
                                ->withQueryString(),
                        ]),
                ])
                ->columnSpanFull()
                ->collapsible()
                ->collapsed(fn ($record) => $record->children->isEmpty()),

            Section::make('Products in this Category')
                ->schema([
                    ViewEntry::make('products_table')
                        ->label(false)
                        ->view('filament.infolists.product-category.products-table')
                        ->viewData(fn ($record) => [
                            'products' => $record->products()
                                ->with(['unit'])
                                ->orderBy('name')
                                ->paginate(10, ['*'], 'productsPage')
                                ->withQueryString(),
                        ]),
                ])
                ->columnSpanFull()
                ->collapsible()
                ->collapsed(fn ($record) => $record->products->isEmpty()),
        ]);
    }
}
