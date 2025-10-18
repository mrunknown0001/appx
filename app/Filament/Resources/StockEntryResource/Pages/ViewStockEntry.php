<?php

namespace App\Filament\Resources\StockEntryResource\Pages;

use App\Filament\Resources\StockEntryResource;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;

class ViewStockEntry extends ViewRecord
{
    protected static string $resource = StockEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('view_supplier')
                ->label('View Supplier')
                ->icon('heroicon-o-user-circle')
                ->url(fn (): string => route('filament.app.resources.suppliers.index', ['tableSearch' => $this->record->supplier_name]))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Stock Entry Overview')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('supplier_name')
                                    ->label('Supplier')
                                    ->badge()
                                    ->color('gray'),

                                TextEntry::make('invoice_number')
                                    ->label('Invoice #')
                                    ->placeholder('No invoice number')
                                    ->copyable(),

                                TextEntry::make('entry_date')
                                    ->label('Entry Date')
                                    ->date()
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('total_quantity')
                                    ->label('Total Quantity')
                                    ->numeric()
                                    ->formatStateUsing(fn ($state) => number_format((int) $state) . ' units')
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('total_cost')
                                    ->label('Total Cost')
                                    ->money('PHP')
                                    ->weight(FontWeight::Bold)
                                    ->color('success')
                                    ->size('lg'),

                                TextEntry::make('items_count')
                                    ->label('Distinct Products')
                                    ->numeric()
                                    ->badge(),
                            ]),
                    ])
                    ->columns(2),
                Section::make('Products')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('Products Received')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('product.name')
                                            ->label('Product')
                                            ->weight(FontWeight::Bold)
                                            ->helperText(fn ($record) => $record->product ? "SKU: {$record->product->sku}" : 'Unknown product'),

                                        TextEntry::make('quantity_received')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->formatStateUsing(function ($state, $record) {
                                                $unit = $record->product?->unit?->abbreviation ?? 'units';
                                                return number_format((int) $state) . " {$unit}";
                                            })
                                            ->badge()
                                            ->color('success'),

                                        TextEntry::make('unit_cost')
                                            ->label('Unit Cost')
                                            ->money('PHP'),

                                        TextEntry::make('total_cost')
                                            ->label('Total Cost')
                                            ->money('PHP')
                                            ->weight(FontWeight::Bold),

                                        TextEntry::make('selling_price')
                                            ->label('Selling Price')
                                            ->money('PHP'),

                                        TextEntry::make('batch_number')
                                            ->label('Batch #')
                                            ->placeholder('No batch number')
                                            ->copyable(),

                                        TextEntry::make('expiry_date')
                                            ->label('Expiry')
                                            ->date()
                                            ->badge()
                                            ->color(function ($state) {
                                                if (!$state) {
                                                    return 'gray';
                                                }

                                                $diff = now()->diffInDays($state, false);
                                                if ($diff < 0) {
                                                    return 'danger';
                                                }
                                                if ($diff <= 30) {
                                                    return 'warning';
                                                }
                                                return 'success';
                                            })
                                            ->formatStateUsing(function ($state) {
                                                if (!$state) {
                                                    return 'No expiry';
                                                }
                                                $diff = now()->diffInDays($state, false);
                                                if ($diff < 0) {
                                                    return 'Expired ' . $state->diffForHumans();
                                                }
                                                return 'Expires ' . $state->diffForHumans();
                                            }),
                                    ])
                                    ->columns(3),

                                TextEntry::make('notes')
                                    ->label('Item Notes')
                                    ->placeholder('No notes for this item')
                                    ->columnSpanFull(),
                            ])
                            // ->emptyStateDescription('No products recorded')
                            ->columns(1),
                    ])
                    ->collapsible(),
                Section::make('Inventory Batches')
                    ->schema([
                        RepeatableEntry::make('inventoryBatches')
                            ->label('Linked Batches')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('product.name')
                                            ->label('Product')
                                            ->weight(FontWeight::Bold)
                                            ->helperText(fn ($record) => $record->product ? "SKU: {$record->product->sku}" : 'Unknown product'),

                                        TextEntry::make('batch_number')
                                            ->label('Batch Number')
                                            ->placeholder('N/A')
                                            ->copyable(),

                                        TextEntry::make('current_quantity')
                                            ->label('Current Qty')
                                            ->numeric()
                                            ->formatStateUsing(function ($state, $record) {
                                                $unit = $record->product?->unit?->abbreviation ?? 'units';
                                                return number_format((float) $state) . " {$unit}";
                                            })
                                            ->badge()
                                            ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),

                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn ($state) => match ($state) {
                                                'active' => 'success',
                                                'expired' => 'danger',
                                                'out_of_stock' => 'warning',
                                                default => 'gray',
                                            }),

                                        TextEntry::make('expiry_date')
                                            ->label('Expiry Date')
                                            ->date()
                                            ->badge()
                                            ->color(function ($state) {
                                                if (!$state) {
                                                    return 'gray';
                                                }

                                                $diff = now()->diffInDays($state, false);
                                                if ($diff < 0) {
                                                    return 'danger';
                                                }
                                                if ($diff <= 30) {
                                                    return 'warning';
                                                }
                                                return 'success';
                                            }),
                                    ]),
                            ])
                            // ->emptyStateDescription('No inventory batches linked yet'),
                    ])
                    ->columns(1)
                    ->visible(fn ($record) => $record->inventoryBatches->isNotEmpty()),
                Section::make('Audit & Notes')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                        TextEntry::make('notes')
                            ->label('Entry Notes')
                            ->placeholder('No additional notes provided.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}