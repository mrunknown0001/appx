<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists\Components\Tabs;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => in_array($record->status, ['pending', 'completed'])),
                
            Actions\Action::make('duplicate')
                ->label('Duplicate Sale')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->action(function ($record) {
                    $newSale = $record->replicate();
                    $newSale->sale_number = 'SALE-' . strtoupper(uniqid());
                    $newSale->sale_date = now();
                    $newSale->status = 'pending';
                    $newSale->save();

                    foreach ($record->saleItems as $item) {
                        $newItem = $item->replicate();
                        $newItem->sale_id = $newSale->id;
                        $newItem->save();
                    }

                    return redirect()->route('filament.admin.resources.sales.view', $newSale);
                }),
                
            Actions\Action::make('print_receipt')
                ->label('Print Receipt')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->action(function () {
                    // Implementation for receipt printing
                    $this->js('window.print()');
                }),
                
            Actions\Action::make('change_status')
                ->label('Change Status')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('status')
                        ->required()
                        ->options([
                            'pending' => 'Pending',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                            'refunded' => 'Refunded',
                        ])
                        ->default(fn ($record) => $record->status),
                ])
                ->action(function (array $data, $record) {
                    $record->update(['status' => $data['status']]);
                    
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Status Updated')
                        ->body("Sale status changed to {$data['status']}.")
                        ->send();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Sale Information')
                    ->tabs([
                        Tabs\Tab::make('Sale Overview')
                            ->schema([
                                Section::make('Sale Details')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('sale_number')
                                                    ->label('Sale Number')
                                                    ->weight(FontWeight::Bold)
                                                    ->size('lg')
                                                    ->badge()
                                                    ->color('primary')
                                                    ->copyable(),

                                                TextEntry::make('sale_date')
                                                    ->label('Sale Date')
                                                    ->dateTime()
                                                    ->badge()
                                                    ->color('success')
                                                    ->formatStateUsing(fn ($record) => 
                                                        $record->sale_date->format('M d, Y g:i A')
                                                    ),

                                                TextEntry::make('status')
                                                    ->badge()
                                                    ->color(fn (string $state): string => match ($state) {
                                                        'pending' => 'warning',
                                                        'completed' => 'success',
                                                        'cancelled' => 'danger',
                                                        'refunded' => 'gray',
                                                        default => 'gray',
                                                    })
                                                    ->formatStateUsing(fn (string $state): string => 
                                                        ucfirst($state)
                                                    ),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('customer_name')
                                                    ->label('Customer Name')
                                                    ->placeholder('Walk-in Customer')
                                                    ->icon('heroicon-o-user')
                                                    ->weight(FontWeight::Medium),

                                                TextEntry::make('customer_phone')
                                                    ->label('Customer Phone')
                                                    ->placeholder('No phone provided')
                                                    ->icon('heroicon-o-phone')
                                                    ->copyable(),
                                            ]),
                                    ]),

                                Section::make('Payment Information')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('payment_method')
                                                    ->label('Payment Method')
                                                    ->badge()
                                                    ->color(fn (string $state): string => match ($state) {
                                                        'cash' => 'success',
                                                        'card' => 'primary',
                                                        'digital_wallet' => 'warning',
                                                        'bank_transfer' => 'info',
                                                        'credit' => 'secondary',
                                                        default => 'gray',
                                                    })
                                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                                        'cash' => 'Cash',
                                                        'card' => 'Card',
                                                        'digital_wallet' => 'Digital Wallet',
                                                        'bank_transfer' => 'Bank Transfer',
                                                        'credit' => 'Credit',
                                                        default => $state,
                                                    }),

                                                TextEntry::make('saleItems_count')
                                                    ->label('Total Items')
                                                    ->getStateUsing(fn ($record) => $record->saleItems->count())
                                                    ->badge()
                                                    ->color('gray')
                                                    ->formatStateUsing(fn ($state) => 
                                                        $state . ' item' . ($state !== 1 ? 's' : '')
                                                    ),
                                            ]),
                                    ]),

                                Section::make('Financial Summary')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('subtotal')
                                                    ->label('Subtotal')
                                                    ->money('PHP')
                                                    ->weight(FontWeight::Medium)
                                                    ->size('lg'),

                                                TextEntry::make('tax_amount')
                                                    ->label('Tax Amount')
                                                    ->money('PHP')
                                                    ->placeholder('₱0.00')
                                                    ->color('warning'),

                                                TextEntry::make('discount_amount')
                                                    ->label('Discount')
                                                    ->money('PHP')
                                                    ->placeholder('₱0.00')
                                                    ->color('danger')
                                                    ->formatStateUsing(fn ($state) => 
                                                        $state > 0 ? '-₱' . number_format($state, 2) : '₱0.00'
                                                    ),

                                                TextEntry::make('total_amount')
                                                    ->label('Total Amount')
                                                    ->money('PHP')
                                                    ->weight(FontWeight::Bold)
                                                    ->size('xl')
                                                    ->color('success'),
                                            ]),
                                    ]),

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
                                                    ->dateTime()
                                                    ->since(),

                                                TextEntry::make('updated_at')
                                                    ->label('Last Updated')
                                                    ->dateTime()
                                                    ->since(),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tabs\Tab::make('Sale Items')
                            ->schema([
                                Section::make('Items Sold')
                                    ->schema([
                                        RepeatableEntry::make('saleItems')
                                            ->schema([
                                                Grid::make(6)
                                                    ->schema([
                                                        TextEntry::make('product.name')
                                                            ->label('Product')
                                                            ->weight(FontWeight::Bold)
                                                            ->columnSpan(2)
                                                            ->description(fn ($record) => 
                                                                "SKU: {$record->product->sku}" . 
                                                                ($record->product->manufacturer ? " | {$record->product->manufacturer}" : "")
                                                            ),

                                                        TextEntry::make('inventoryBatch.batch_number')
                                                            ->label('Batch')
                                                            ->badge()
                                                            ->color('gray')
                                                            ->columnSpan(1)
                                                            ->description(fn ($record) => 
                                                                "Exp: {$record->inventoryBatch->expiry_date->format('M d, Y')}"
                                                            ),

                                                        TextEntry::make('quantity')
                                                            ->label('Qty')
                                                            ->badge()
                                                            ->color('primary')
                                                            ->columnSpan(1)
                                                            ->formatStateUsing(fn ($record) => 
                                                                number_format($record->quantity) . ' ' . 
                                                                ($record->product->unit->abbreviation ?? 'units')
                                                            ),

                                                        TextEntry::make('unit_price')
                                                            ->label('Unit Price')
                                                            ->money('PHP')
                                                            ->columnSpan(1),

                                                        TextEntry::make('total_price')
                                                            ->label('Total')
                                                            ->money('PHP')
                                                            ->weight(FontWeight::Bold)
                                                            ->color('success')
                                                            ->columnSpan(1),
                                                    ]),

                                                Grid::make(4)
                                                    ->schema([
                                                        TextEntry::make('discount_amount')
                                                            ->label('Item Discount')
                                                            ->money('PHP')
                                                            ->placeholder('₱0.00')
                                                            ->color('danger')
                                                            ->columnSpan(1)
                                                            ->formatStateUsing(fn ($state) => 
                                                                $state > 0 ? '-₱' . number_format($state, 2) : '₱0.00'
                                                            ),

                                                        TextEntry::make('product.category.name')
                                                            ->label('Category')
                                                            ->badge()
                                                            ->color('info')
                                                            ->columnSpan(1),

                                                        TextEntry::make('product.generic_name')
                                                            ->label('Generic Name')
                                                            ->placeholder('Not specified')
                                                            ->columnSpan(1),

                                                        TextEntry::make('created_at')
                                                            ->label('Added')
                                                            ->since()
                                                            ->columnSpan(1),
                                                    ]),
                                            ])
                                            ->contained(false)
                                            ->grid(1),
                                    ]),
                            ]),

                        Tabs\Tab::make('Analytics')
                            ->schema([
                                Section::make('Sale Analytics')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('items_total_quantity')
                                                    ->label('Total Quantity Sold')
                                                    ->getStateUsing(fn ($record) => 
                                                        $record->saleItems->sum('quantity')
                                                    )
                                                    ->badge()
                                                    ->color('primary')
                                                    ->formatStateUsing(fn ($state) => 
                                                        number_format($state) . ' units'
                                                    ),

                                                TextEntry::make('avg_item_price')
                                                    ->label('Average Item Price')
                                                    ->getStateUsing(function ($record) {
                                                        $items = $record->saleItems;
                                                        return $items->count() > 0 ? 
                                                            $items->sum('total_price') / $items->count() : 0;
                                                    })
                                                    ->money('PHP')
                                                    ->badge()
                                                    ->color('info'),

                                                TextEntry::make('total_discounts')
                                                    ->label('Total Item Discounts')
                                                    ->getStateUsing(fn ($record) => 
                                                        $record->saleItems->sum('discount_amount') + $record->discount_amount
                                                    )
                                                    ->money('PHP')
                                                    ->badge()
                                                    ->color('danger')
                                                    ->formatStateUsing(fn ($state) => 
                                                        $state > 0 ? '-₱' . number_format($state, 2) : '₱0.00'
                                                    ),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('profit_margin')
                                                    ->label('Estimated Profit Margin')
                                                    ->getStateUsing(function ($record) {
                                                        $totalCost = $record->saleItems->sum(function ($item) {
                                                            return $item->quantity * ($item->inventoryBatch->stockEntry->unit_cost ?? 0);
                                                        });
                                                        $totalRevenue = $record->total_amount;
                                                        
                                                        if ($totalRevenue > 0) {
                                                            $profit = $totalRevenue - $totalCost;
                                                            return ($profit / $totalRevenue) * 100;
                                                        }
                                                        return 0;
                                                    })
                                                    ->badge()
                                                    ->color('success')
                                                    ->formatStateUsing(fn ($state) => 
                                                        number_format($state, 1) . '%'
                                                    ),

                                                TextEntry::make('days_since_sale')
                                                    ->label('Days Since Sale')
                                                    ->getStateUsing(fn ($record) => 
                                                        now()->diffInDays($record->sale_date)
                                                    )
                                                    ->badge()
                                                    ->color('gray')
                                                    ->formatStateUsing(fn ($state) => 
                                                        $state . ' day' . ($state !== 1 ? 's' : '') . ' ago'
                                                    ),
                                            ]),
                                    ]),

                                Section::make('Product Breakdown')
                                    ->schema([
                                        ViewEntry::make('products_chart')
                                            ->view('filament.infolists.sale-products-chart')
                                            ->viewData(fn ($record) => [
                                                'saleItems' => $record->saleItems->load(['product', 'inventoryBatch'])
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),
                    ])
                    ->persistTabInQueryString(),
            ]);
    }
}