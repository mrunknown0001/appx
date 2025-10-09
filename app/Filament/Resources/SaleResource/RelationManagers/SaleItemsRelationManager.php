<?php

namespace App\Filament\Resources\SaleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Product;
use App\Models\InventoryBatch;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;

class SaleItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'saleItems';

    protected static ?string $title = 'Sale Items';

    protected static ?string $modelLabel = 'Sale Item';

    protected static ?string $pluralModelLabel = 'Sale Items';

    protected static ?string $icon = 'heroicon-o-shopping-bag';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Product $record): string => 
                                "{$record->name} ({$record->sku}) - Stock: {$record->getCurrentStock()}"
                            )
                            ->searchable(['name', 'sku', 'generic_name', 'manufacturer'])
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if ($state) {
                                    $latestSellingPrice = \App\Models\StockEntry::query()
                                        ->where('product_id', $state)
                                        ->whereNotNull('selling_price')
                                        ->latest('entry_date')
                                        ->value('selling_price');

                                    $set(
                                        'unit_price',
                                        $latestSellingPrice !== null
                                            ? number_format((float) $latestSellingPrice, 2, '.', '')
                                            : number_format(0, 2, '.', '')
                                    );

                                    // Clear the batch selection
                                    $set('inventory_batch_id', null);
                                    // Clear previous calculations
                                    $set('total_price', null);
                                } else {
                                    $set('unit_price', number_format(0, 2, '.', ''));
                                }
                            })
                            ->helperText('Search by product name, SKU, or manufacturer'),

                        Select::make('inventory_batch_id')
                            ->label('Inventory Batch')
                            ->relationship('inventoryBatch', 'batch_number')
                            ->getOptionLabelFromRecordUsing(fn (InventoryBatch $record): string => 
                                "{$record->batch_number} (Available: {$record->current_quantity}, Exp: {$record->expiry_date->format('M Y')})"
                            )
                            ->options(function (Get $get) {
                                $productId = $get('product_id');
                                if (!$productId) return [];
                                
                                return InventoryBatch::where('product_id', $productId)
                                    ->where('current_quantity', '>', 0)
                                    ->where('expiry_date', '>', now())
                                    ->where('status', 'active')
                                    ->orderBy('expiry_date', 'asc') // FIFO - First In, First Out
                                    ->get()
                                    ->mapWithKeys(function ($batch) {
                                        return [
                                            $batch->id => "{$batch->batch_number} (Qty: {$batch->current_quantity}, Exp: {$batch->expiry_date->format('M Y')})"
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->live()
                            ->helperText('Only active batches with available stock are shown')
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if ($state) {
                                    $batch = InventoryBatch::with('stockEntry')->find($state);

                                    if ($batch?->stockEntry?->selling_price !== null) {
                                        $set('unit_price', number_format((float) $batch->stockEntry->selling_price, 2, '.', ''));
                                    }

                                    $this->calculateTotal($set, $get);
                                }
                            }),
                    ]),

                Grid::make(3)
                    ->schema([
                        TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $this->calculateTotal($set, $get, $state);
                            })
                            ->helperText('Quantity to sell'),

                        TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->required()
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(0)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $this->calculateTotal($set, $get, null, $state);
                            })
                            ->helperText('Price per unit'),

                        TextInput::make('discount_amount')
                            ->label('Discount')
                            ->numeric()
                            ->prefix('₱')
                            ->default(0)
                            ->minValue(0)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $this->calculateTotal($set, $get, null, null, $state);
                            })
                            ->helperText('Discount amount'),
                    ]),

                TextInput::make('total_price')
                    ->label('Total Price')
                    ->required()
                    ->numeric()
                    ->prefix('₱')
                    ->readOnly()
                    ->dehydrated()
                    ->helperText('Automatically calculated total'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->description(fn ($record): string => 
                        "SKU: {$record->product->sku}" . 
                        ($record->product->manufacturer ? " | {$record->product->manufacturer}" : "")
                    ),

                TextColumn::make('inventoryBatch.batch_number')
                    ->label('Batch')
                    ->badge()
                    ->color('gray')
                    ->description(fn ($record): string => 
                        "Exp: {$record->inventoryBatch->expiry_date->format('M d, Y')}"
                    ),

                TextColumn::make('quantity')
                    ->numeric()
                    ->alignCenter()
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($record): string => 
                        number_format($record->quantity) . ' ' . 
                        ($record->product->unit->abbreviation ?? 'units')
                    ),

                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('PHP')
                    ->alignEnd()
                    ->weight(FontWeight::Medium),

                TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money('PHP')
                    ->alignEnd()
                    ->placeholder('No discount')
                    ->color('danger')
                    ->formatStateUsing(fn ($state): string => 
                        $state > 0 ? '-₱' . number_format($state, 2) : '₱0.00'
                    ),

                TextColumn::make('total_price')
                    ->label('Total')
                    ->money('PHP')
                    ->alignEnd()
                    ->weight(FontWeight::Bold)
                    ->color('success')
                    ->size('lg'),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Validate inventory availability before creating
                        $batch = InventoryBatch::find($data['inventory_batch_id']);
                        if (!$batch) {
                            Notification::make()
                                ->danger()
                                ->title('Inventory Batch Not Found')
                                ->body('The selected inventory batch does not exist.')
                                ->send();
                            
                            throw new \Exception('Inventory batch not found');
                        }
                        
                        if ($batch->current_quantity < $data['quantity']) {
                            Notification::make()
                                ->danger()
                                ->title('Insufficient Stock')
                                ->body("Only {$batch->current_quantity} units available in this batch.")
                                ->send();
                            
                            throw new \Exception('Insufficient stock available');
                        }
                        
                        // Add debugging
                        \Log::info('Creating sale item', [
                            'batch_id' => $batch->id,
                            'current_quantity' => $batch->current_quantity,
                            'requested_quantity' => $data['quantity']
                        ]);
                        
                        return $data;
                    })
                    ->after(function ($record) {
                        try {
                            // Reload the record to ensure we have the latest data
                            $record = $record->fresh(['inventoryBatch']);
                            
                            // Update inventory batch quantity
                            $batch = $record->inventoryBatch;
                            if (!$batch) {
                                \Log::error('Inventory batch not found for sale item', ['sale_item_id' => $record->id]);
                                return;
                            }
                            
                            $newQuantity = $batch->current_quantity - $record->quantity;
                            
                            // Add debugging
                            \Log::info('Updating inventory batch', [
                                'batch_id' => $batch->id,
                                'old_quantity' => $batch->current_quantity,
                                'sale_quantity' => $record->quantity,
                                'new_quantity' => $newQuantity
                            ]);
                            
                            $batch->update([
                                'current_quantity' => max(0, $newQuantity), // Ensure we don't go negative
                                'status' => $newQuantity <= 0 ? 'out_of_stock' : 'active'
                            ]);
                            
                            // Verify the update worked
                            $batch->refresh();
                            \Log::info('Inventory batch updated', [
                                'batch_id' => $batch->id,
                                'updated_quantity' => $batch->current_quantity,
                                'status' => $batch->status
                            ]);
                            
                            // Update sale totals
                            $this->updateSaleTotals();
                            
                            // Show success notification
                            Notification::make()
                                ->success()
                                ->title('Sale Item Added')
                                ->body("Inventory updated: {$record->quantity} units deducted from batch {$batch->batch_number}")
                                ->send();
                                
                        } catch (\Exception $e) {
                            \Log::error('Error updating inventory after sale item creation', [
                                'error' => $e->getMessage(),
                                'sale_item_id' => $record->id ?? null
                            ]);
                            
                            Notification::make()
                                ->danger()
                                ->title('Inventory Update Failed')
                                ->body('Sale item created but inventory was not updated. Please check the logs.')
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, $record): array {
                        // Store original quantity for inventory adjustment
                        $data['_original_quantity'] = $record->quantity;
                        return $data;
                    })
                    ->after(function ($record, array $data) {
                        // Adjust inventory based on quantity change
                        if (isset($data['_original_quantity'])) {
                            $quantityDifference = $record->quantity - $data['_original_quantity'];
                            if ($quantityDifference != 0) {
                                $batch = $record->inventoryBatch;
                                if ($batch) {
                                    $newQuantity = $batch->current_quantity - $quantityDifference;
                                    $batch->update([
                                        'current_quantity' => max(0, $newQuantity),
                                        'status' => $newQuantity <= 0 ? 'out_of_stock' : 'active'
                                    ]);
                                }
                            }
                        }
                        
                        // Update sale totals
                        $this->updateSaleTotals();
                    }),
                    
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        // Return stock to inventory when item is deleted
                        $batch = $record->inventoryBatch;
                        if ($batch) {
                            $batch->update([
                                'current_quantity' => $batch->current_quantity + $record->quantity,
                                'status' => 'active'
                            ]);
                        }
                    })
                    ->after(function () {
                        // Update sale totals
                        $this->updateSaleTotals();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Return stock to inventory for all deleted items
                            foreach ($records as $record) {
                                $batch = $record->inventoryBatch;
                                if ($batch) {
                                    $batch->update([
                                        'current_quantity' => $batch->current_quantity + $record->quantity,
                                        'status' => 'active'
                                    ]);
                                }
                            }
                        })
                        ->after(function () {
                            // Update sale totals
                            $this->updateSaleTotals();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('No items in this sale')
            ->emptyStateDescription('Add some products to this sale using the "Create" button above.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }

    protected function calculateTotal(Set $set, Get $get, $quantity = null, $unitPrice = null, $discountAmount = null): void
    {
        $quantity = $quantity ?? (int) $get('quantity');
        $unitPrice = $unitPrice ?? (float) $get('unit_price');
        $discountAmount = $discountAmount ?? (float) $get('discount_amount');
        
        $subtotal = $quantity * $unitPrice;
        $total = $subtotal - $discountAmount;
        
        $set('total_price', number_format(max(0, $total), 2, '.', ''));
    }

    protected function updateSaleTotals(): void
    {
        $sale = $this->getOwnerRecord();
        
        // Force refresh the relationship to get current data
        $sale->load('saleItems');
        
        $subtotal = $sale->saleItems->sum('total_price');
        
        // Get current form state for tax and discount (in case user changed them)
        $taxAmount = $sale->tax_amount ?? 0;
        $saleDiscount = $sale->discount_amount ?? 0;
        
        $total = $subtotal + $taxAmount - $saleDiscount;
        
        // Update the sale record
        $sale->update([
            'subtotal' => $subtotal,
            'total_amount' => max(0, $total),
        ]);
        
        // Refresh the parent form to show updated values
        $this->dispatch('refresh-parent-form');
        
        // Also refresh the owner record to ensure UI sync
        $this->refreshOwnerRecord();
        
        Notification::make()
            ->success()
            ->title('Sale Updated')
            ->body("Subtotal: ₱" . number_format($subtotal, 2) . " | Total: ₱" . number_format($total, 2))
            ->send();
    }

    // Add this method as well to refresh the owner record
    protected function refreshOwnerRecord(): void
    {
        $this->ownerRecord = $this->ownerRecord->fresh();
    }
}