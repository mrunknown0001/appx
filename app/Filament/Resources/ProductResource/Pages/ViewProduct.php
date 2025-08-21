<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Grid;
use Filament\Support\Enums\FontWeight;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Product Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Product Name')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),

                                TextEntry::make('sku')
                                    ->label('SKU')
                                    ->badge()
                                    ->color('gray')
                                    ->copyable(),

                                TextEntry::make('barcode')
                                    ->placeholder('No barcode')
                                    ->copyable(),

                                TextEntry::make('category.name')
                                    ->label('Category'),

                                TextEntry::make('unit.name')
                                    ->label('Unit')
                                    ->formatStateUsing(fn ($record) => "{$record->unit->name} ({$record->unit->abbreviation})"),

                                TextEntry::make('description')
                                    ->placeholder('No description')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Medical Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('generic_name')
                                    ->label('Generic Name')
                                    ->placeholder('Not specified'),

                                TextEntry::make('manufacturer')
                                    ->placeholder('Not specified'),

                                TextEntry::make('strength')
                                    ->placeholder('Not specified'),

                                TextEntry::make('dosage_form')
                                    ->label('Dosage Form')
                                    ->placeholder('Not specified')
                                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : null),
                            ]),
                    ]),

                Section::make('Inventory & Status')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('current_stock')
                                    ->label('Current Stock')
                                    ->getStateUsing(fn ($record) => $record->getCurrentStock())
                                    ->badge()
                                    ->color(fn ($record): string => match (true) {
                                        $record->getCurrentStock() <= 0 => 'danger',
                                        $record->getCurrentStock() <= $record->min_stock_level => 'warning',
                                        $record->getCurrentStock() >= $record->max_stock_level => 'info',
                                        default => 'success',
                                    }),

                                TextEntry::make('min_stock_level')
                                    ->label('Min Stock Level'),

                                TextEntry::make('max_stock_level')
                                    ->label('Max Stock Level'),

                                TextEntry::make('current_price')
                                    ->label('Current Price')
                                    ->getStateUsing(fn ($record) => '₱' . number_format($record->getCurrentPrice(), 2))
                                    ->weight(FontWeight::Bold),

                                IconEntry::make('is_prescription_required')
                                    ->label('Prescription Required')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('warning')
                                    ->falseColor('gray'),

                                IconEntry::make('is_active')
                                    ->label('Active Status')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                            ]),
                    ]),

                Section::make('Timestamps')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }
}