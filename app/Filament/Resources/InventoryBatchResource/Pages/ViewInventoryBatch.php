<?php

namespace App\Filament\Resources\InventoryBatchResource\Pages;

use App\Filament\Resources\InventoryBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Support\Enums\FontWeight;

class ViewInventoryBatch extends ViewRecord
{
    protected static string $resource = InventoryBatchResource::class;

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

            Actions\Action::make('view_stock_entry')
                ->label('View Stock Entry')
                ->icon('heroicon-o-document-text')
                ->url(fn (): string => 
                    route('filament.app.resources.stock-entries.view', $this->record->stockEntry)
                )
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Batch Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('batch_number')
                                    ->label('Batch Number')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg')
                                    ->copyable()
                                    ->placeholder('No batch number'),

                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn ($record) => match ($record->status) {
                                        'active' => 'success',
                                        'expired' => 'danger',
                                        'depleted' => 'warning',
                                        default => 'gray',
                                    }),

                                TextEntry::make('location')
                                    ->label('Storage Location')
                                    ->placeholder('Not specified')
                                    ->icon('heroicon-o-map-pin'),
                            ]),
                    ]),

                Section::make('Product Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Product Name')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),

                                TextEntry::make('product.sku')
                                    ->label('Product SKU')
                                    ->copyable()
                                    ->icon('heroicon-o-hashtag'),

                                TextEntry::make('product.category.name')
                                    ->label('Category')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('No category'),

                                TextEntry::make('product.unit.name')
                                    ->label('Unit of Measure')
                                    ->placeholder('No unit specified'),
                            ]),
                    ]),

                Section::make('Quantity Details')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('initial_quantity')
                                    ->label('Initial Quantity')
                                    ->numeric()
                                    ->suffix(fn ($record) => $record->product?->unit?->abbreviation ?? 'units')
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),

                                TextEntry::make('current_quantity')
                                    ->label('Current Quantity')
                                    ->numeric()
                                    ->suffix(fn ($record) => $record->product?->unit?->abbreviation ?? 'units')
                                    ->weight(FontWeight::Bold)
                                    ->color(function ($record) {
                                        if ($record->current_quantity <= 0) return 'danger';
                                        if ($record->product && $record->current_quantity <= $record->product->min_stock_level) {
                                            return 'warning';
                                        }
                                        return 'success';
                                    }),

                                TextEntry::make('used_quantity')
                                    ->label('Used Quantity')
                                    ->state(fn ($record) => $record->initial_quantity - $record->current_quantity)
                                    ->numeric()
                                    ->suffix(fn ($record) => $record->product?->unit?->abbreviation ?? 'units')
                                    ->color('info'),

                                TextEntry::make('usage_percentage')
                                    ->label('Usage Percentage')
                                    ->state(function ($record) {
                                        if ($record->initial_quantity <= 0) return '0%';
                                        $used = $record->initial_quantity - $record->current_quantity;
                                        return round(($used / $record->initial_quantity) * 100, 1) . '%';
                                    })
                                    ->color(function ($record) {
                                        if ($record->initial_quantity <= 0) return 'gray';
                                        $used = $record->initial_quantity - $record->current_quantity;
                                        $percentage = ($used / $record->initial_quantity) * 100;
                                        if ($percentage >= 80) return 'danger';
                                        if ($percentage >= 60) return 'warning';
                                        return 'success';
                                    }),
                            ]),
                    ]),

                Section::make('Dates & Timeline')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('stockEntry.entry_date')
                                    ->label('Stock Entry Date')
                                    ->date('M d, Y')
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('expiry_date')
                                    ->label('Expiry Date')
                                    ->date('M d, Y')
                                    ->badge()
                                    ->color(function ($record) {
                                        $daysUntilExpiry = $record->expiry_date->diffInDays(now(), false);
                                        if ($daysUntilExpiry > 0) return 'danger'; // Expired
                                        if ($daysUntilExpiry > -30) return 'warning'; // Expires soon
                                        return 'success';
                                    })
                                    ->helperText(function ($record) {
                                        $daysUntilExpiry = $record->expiry_date->diffInDays(now(), false);
                                        if ($daysUntilExpiry > 0) {
                                            return "Expired " . $record->expiry_date->diffForHumans();
                                        }
                                        return "Expires " . $record->expiry_date->diffForHumans();
                                    }),

                                TextEntry::make('days_until_expiry')
                                    ->label('Days Until Expiry')
                                    ->state(function ($record) {
                                        $days = $record->expiry_date->diffInDays(now(), false);
                                        if ($days > 0) return "Expired ({$days} days ago)";
                                        return abs($days) . " days";
                                    })
                                    ->color(function ($record) {
                                        $days = $record->expiry_date->diffInDays(now(), false);
                                        if ($days > 0) return 'danger'; // Expired
                                        if ($days > -30) return 'warning'; // Expires soon
                                        return 'success';
                                    }),
                            ]),
                    ]),

                Section::make('Supply Chain Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('stockEntry.supplier_name')
                                    ->label('Supplier')
                                    ->badge()
                                    ->color('gray'),

                                TextEntry::make('stockEntry.invoice_number')
                                    ->label('Invoice Number')
                                    ->copyable()
                                    ->placeholder('No invoice number'),

                                TextEntry::make('stockEntry.unit_cost')
                                    ->label('Unit Cost')
                                    ->money('PHP')
                                    ->placeholder('Not specified'),

                                TextEntry::make('stockEntry.total_cost')
                                    ->label('Total Cost')
                                    ->money('PHP')
                                    ->placeholder('Not specified'),
                            ]),
                    ]),

                Section::make('Stock Alerts')
                    ->schema([
                        TextEntry::make('stock_alerts')
                            ->label('Current Alerts')
                            ->state(function ($record) {
                                $alerts = [];
                                
                                // Check if expired
                                if ($record->expiry_date->isPast()) {
                                    $alerts[] = '🔴 This batch has expired';
                                }
                                
                                // Check if expiring soon
                                $daysUntilExpiry = $record->expiry_date->diffInDays(now(), false);
                                if ($daysUntilExpiry <= 30 && $daysUntilExpiry > 0) {
                                    $alerts[] = '🟡 Expires in ' . $daysUntilExpiry . ' days';
                                }
                                
                                // Check low stock
                                if ($record->product && $record->current_quantity <= $record->product->min_stock_level) {
                                    $alerts[] = '🟠 Low stock alert - Below minimum level';
                                }
                                
                                // Check if depleted
                                if ($record->current_quantity <= 0) {
                                    $alerts[] = '🔴 Out of stock';
                                }
                                
                                return empty($alerts) ? '✅ No alerts' : implode("\n", $alerts);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Record Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('M d, Y H:i:s'),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('M d, Y H:i:s')
                                    ->helperText(fn ($record) => $record->updated_at->diffForHumans()),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}