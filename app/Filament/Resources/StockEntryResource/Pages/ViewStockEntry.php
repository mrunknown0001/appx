<?php

namespace App\Filament\Resources\StockEntryResource\Pages;

use App\Filament\Resources\StockEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Support\Enums\FontWeight;

class ViewStockEntry extends ViewRecord
{
    protected static string $resource = StockEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('view_product')
                ->label('View Product')
                ->icon('heroicon-o-cube')
                ->url(fn (): string => 
                    route('filament.app.resources.products.view', $this->record->product)
                )
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Stock Entry Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Product')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg')
                                    ->helperText(fn ($record) => "SKU: {$record->product->sku}"),

                                TextEntry::make('supplier_name')
                                    ->label('Supplier')
                                    ->badge()
                                    ->color('gray'),

                                TextEntry::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->placeholder('No invoice number')
                                    ->copyable(),

                                TextEntry::make('batch_number')
                                    ->label('Batch Number')
                                    ->placeholder('No batch number')
                                    ->copyable(),

                                TextEntry::make('entry_date')
                                    ->label('Entry Date')
                                    ->date()
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('expiry_date')
                                    ->label('Expiry Date')
                                    ->date()
                                    ->badge()
                                    ->color(function ($record) {
                                        $daysUntilExpiry = $record->expiry_date->diffInDays(now(), false);
                                        if ($daysUntilExpiry > 0) return 'danger'; // Expired
                                        if ($daysUntilExpiry > -30) return 'warning'; // Expires soon
                                        return 'success';
                                    })
                                    ->formatStateUsing(function ($record)  {
                                        $daysUntilExpiry = $record->expiry_date->diffInDays(now(), false);
                                        if ($daysUntilExpiry > 0) {
                                            return "Expired " . $record->expiry_date->diffForHumans();
                                        }
                                        return "Expires " . $record->expiry_date->diffForHumans();
                                    }),
                            ])
                    ]),

                Section::make('Quantity & Pricing')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('quantity_received')
                                    ->label('Quantity Received')
                                    ->numeric()
                                    ->badge()
                                    ->color('success')
                                    ->formatStateUsing(fn ($record) => 
                                        number_format($record->quantity_received) . ' ' . 
                                        ($record->product->unit->abbreviation ?? 'units')
                                    ),

                                TextEntry::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->money('PHP')
                                    ->weight(FontWeight::Medium),

                                TextEntry::make('total_cost')
                                    ->label('Total Cost')
                                    ->money('PHP')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg')
                                    ->color('success'),
                            ])
                    ]),

                Section::make('Product Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('product.category.name')
                                    ->label('Category'),

                                TextEntry::make('product.unit.name')
                                    ->label('Unit')
                                    ->formatStateUsing(fn ($record) => 
                                        "{$record->product->unit->name} ({$record->product->unit->abbreviation})"
                                    ),

                                TextEntry::make('product.manufacturer')
                                    ->label('Manufacturer')
                                    ->placeholder('Not specified'),

                                TextEntry::make('product.generic_name')
                                    ->label('Generic Name')
                                    ->placeholder('Not specified'),
                            ])
                    ])
                    ->collapsible(),

                Section::make('Additional Information')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No additional notes')
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ])
                    ])
                    ->collapsible(),

                Section::make('Related Inventory')
                    ->schema([
                        TextEntry::make('inventoryBatch.current_quantity')
                            ->label('Current Inventory Quantity')
                            ->numeric()
                            ->placeholder('No inventory batch found')
                            ->badge()
                            ->color(fn ($record) => 
                                $record->inventoryBatch?->current_quantity > 0 ? 'success' : 'danger'
                            )
                            ->formatStateUsing(fn ($record) => 
                                $record->inventoryBatch ? 
                                number_format($record->inventoryBatch->current_quantity) . ' ' . 
                                ($record->product->unit->abbreviation ?? 'units') : 
                                'No inventory batch'
                            ),

                        TextEntry::make('inventoryBatch.status')
                            ->label('Batch Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'active' => 'success',
                                'expired' => 'danger',
                                'recalled' => 'warning',
                                default => 'gray',
                            })
                            ->placeholder('No inventory batch'),

                        TextEntry::make('inventoryBatch.location')
                            ->label('Storage Location')
                            ->placeholder('Not specified'),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => $record->inventoryBatch !== null),
            ]);
    }
}