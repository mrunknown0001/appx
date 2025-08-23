<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers;
use App\Models\Sale;
use App\Models\Product;
use App\Models\InventoryBatch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;


class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Sales';

    protected static ?string $modelLabel = 'Sale';

    protected static ?string $pluralModelLabel = 'Sales';

    protected static ?string $navigationGroup = 'Sales Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'sale_number';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Sale Information')
                    ->tabs([
                        Tabs\Tab::make('Sale Details')
                            ->schema([
                                Section::make('Sale Information')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('sale_number')
                                                    ->required()
                                                    ->unique(ignoreRecord: true)
                                                    ->maxLength(255)
                                                    ->placeholder('Auto-generated if empty')
                                                    ->default(fn () => 'SALE-' . strtoupper(uniqid()))
                                                    ->helperText('Unique sale identifier'),

                                                DateTimePicker::make('sale_date')
                                                    ->required()
                                                    ->default(now())
                                                    ->helperText('Date and time of sale'),

                                                Select::make('status')
                                                    ->required()
                                                    ->options([
                                                        'pending' => 'Pending',
                                                        'completed' => 'Completed',
                                                        'cancelled' => 'Cancelled',
                                                        'refunded' => 'Refunded',
                                                    ])
                                                    ->default('completed')
                                                    ->native(false),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('customer_name')
                                                    ->maxLength(255)
                                                    ->placeholder('Customer name (optional)'),

                                                TextInput::make('customer_phone')
                                                    ->tel()
                                                    ->maxLength(255)
                                                    ->placeholder('Customer phone (optional)'),
                                            ]),

                                        Select::make('payment_method')
                                            ->required()
                                            ->options([
                                                'cash' => 'Cash',
                                                'card' => 'Card',
                                                'digital_wallet' => 'Digital Wallet',
                                                'bank_transfer' => 'Bank Transfer',
                                                'credit' => 'Credit',
                                            ])
                                            ->default('cash')
                                            ->native(false),

                                        Textarea::make('notes')
                                            ->placeholder('Additional notes about this sale')
                                            ->maxLength(1000)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Sale Items')
                            ->schema([
                                Section::make('Products Sold')
                                    ->schema([
                                        Repeater::make('saleItems')
                                            ->relationship()
                                            ->schema([
                                                Grid::make(4)
                                                    ->schema([
                                                        Select::make('product_id')
                                                            ->label('Product')
                                                            ->relationship('product', 'name')
                                                            ->getOptionLabelFromRecordUsing(fn (Product $record): string => "{$record->name} ({$record->sku})")
                                                            ->searchable(['name', 'sku', 'generic_name'])
                                                            ->preload()
                                                            ->required()
                                                            ->live()
                                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                                if ($state) {
                                                                    $product = Product::find($state);
                                                                    if ($product) {
                                                                        $set('unit_price', $product->getCurrentPrice());
                                                                        // Update available batches
                                                                        $set('inventory_batch_id', null);
                                                                    }
                                                                }
                                                            }),

                                                        Select::make('inventory_batch_id')
                                                            ->label('Batch')
                                                            ->relationship('inventoryBatch', 'batch_number')
                                                            ->getOptionLabelFromRecordUsing(fn (InventoryBatch $record): string => 
                                                                "{$record->batch_number} (Qty: {$record->current_quantity}, Exp: {$record->expiry_date->format('M Y')})")
                                                            ->options(function (Get $get) {
                                                                $productId = $get('product_id');
                                                                if (!$productId) return [];
                                                                
                                                                return InventoryBatch::where('product_id', $productId)
                                                                    ->where('current_quantity', '>', 0)
                                                                    ->where('expiry_date', '>', now())
                                                                    ->where('status', 'active')
                                                                    ->get()
                                                                    ->pluck('batch_number', 'id');
                                                            })
                                                            ->required()
                                                            ->searchable()
                                                            ->preload(),

                                                        TextInput::make('quantity')
                                                            ->required()
                                                            ->numeric()
                                                            ->default(1)
                                                            ->minValue(1)
                                                            ->live(debounce: 500)
                                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                                $unitPrice = (float) $get('unit_price');
                                                                $discountAmount = (float) $get('discount_amount');
                                                                $total = ($state * $unitPrice) - $discountAmount;
                                                                $set('total_price', number_format($total, 2, '.', ''));
                                                            }),

                                                        TextInput::make('unit_price')
                                                            ->required()
                                                            ->numeric()
                                                            ->prefix('₱')
                                                            ->minValue(0)
                                                            ->live(debounce: 500)
                                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                                $quantity = (int) $get('quantity');
                                                                $discountAmount = (float) $get('discount_amount');
                                                                $total = ($quantity * $state) - $discountAmount;
                                                                $set('total_price', number_format($total, 2, '.', ''));
                                                            }),
                                                    ]),

                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('discount_amount')
                                                            ->numeric()
                                                            ->prefix('₱')
                                                            ->default(0)
                                                            ->minValue(0)
                                                            ->live(debounce: 500)
                                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                                $quantity = (int) $get('quantity');
                                                                $unitPrice = (float) $get('unit_price');
                                                                $total = ($quantity * $unitPrice) - $state;
                                                                $set('total_price', number_format($total, 2, '.', ''));
                                                            }),

                                                        TextInput::make('total_price')
                                                            ->required()
                                                            ->numeric()
                                                            ->prefix('₱')
                                                            ->readOnly()
                                                            ->dehydrated(),
                                                    ]),
                                            ])
                                            ->collapsible()
                                            ->cloneable()
                                            ->deletable()
                                            ->defaultItems(1)
                                            ->itemLabel(fn (array $state): ?string => 
                                                $state['product_id'] ? Product::find($state['product_id'])?->name : 'New Item'
                                            ),
                                    ]),
                            ]),

                        Tabs\Tab::make('Summary')
                            ->schema([
                                Section::make('Sale Summary')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('subtotal')
                                                    ->numeric()
                                                    ->prefix('₱')
                                                    ->readOnly()
                                                    ->dehydrated()
                                                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 2))
                                                    ->extraAttributes(['class' => 'font-bold'])
                                                    ->live(),

                                                TextInput::make('tax_amount')
                                                    ->numeric()
                                                    ->prefix('₱')
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                                        $subtotal = (float) $get('subtotal');
                                                        $tax = (float) $get('tax_amount');
                                                        $discount = (float) $get('discount_amount');
                                                        $total = $subtotal + $tax - $discount;
                                                        $set('total_amount', max(0, $total));
                                                    }),

                                                TextInput::make('discount_amount')
                                                    ->numeric()
                                                    ->prefix('₱')
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                                        $subtotal = (float) $get('subtotal');
                                                        $tax = (float) $get('tax_amount');
                                                        $discount = (float) $get('discount_amount');
                                                        $total = $subtotal + $tax - $discount;
                                                        $set('total_amount', max(0, $total));
                                                    }),

                                                TextInput::make('total_amount')
                                                    ->numeric()
                                                    ->prefix('₱')
                                                    ->readOnly()
                                                    ->dehydrated()
                                                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 2))
                                                    ->extraAttributes(['class' => 'font-bold text-green-600'])
                                                    ->live(),
                                            ]),

                                        Placeholder::make('calculation_info')
                                            ->content('Totals will be automatically calculated based on sale items. Tax and discount can be adjusted above.')
                                            ->columnSpanFull(),
                                    ])
                                    ->extraAttributes(['id' => 'sale-summary-section']),
                            ]),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sale_number')
                    ->label('Sale Number')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable(),

                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('Walk-in Customer')
                    ->description(fn (Sale $record): string => $record->customer_phone ?? ''),

                TextColumn::make('sale_date')
                    ->label('Sale Date')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->description(fn (Sale $record): string => $record->sale_date->format('M d, Y g:i A')),

                TextColumn::make('saleItems_count')
                    ->label('Items')
                    ->counts('saleItems')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('PHP')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('success'),

                BadgeColumn::make('payment_method')
                    ->label('Payment')
                    ->colors([
                        'success' => 'cash',
                        'primary' => 'card',
                        'warning' => 'digital_wallet',
                        'info' => 'bank_transfer',
                        'secondary' => 'credit',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'digital_wallet' => 'Digital Wallet',
                        'bank_transfer' => 'Bank Transfer',
                        'credit' => 'Credit',
                        default => $state,
                    }),

                BadgeColumn::make('status')
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'pending',
                        'danger' => ['cancelled', 'refunded'],
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),

                SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'digital_wallet' => 'Digital Wallet',
                        'bank_transfer' => 'Bank Transfer',
                        'credit' => 'Credit',
                    ])
                    ->multiple(),

                Filter::make('sale_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('sale_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('sale_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'From: ' . Carbon::parse($data['from'])->toFormattedDateString();
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until: ' . Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),

                Filter::make('high_value_sales')
                    ->label('High Value Sales (₱5,000+)')
                    ->query(fn (Builder $query): Builder => $query->where('total_amount', '>=', 5000))
                    ->toggle(),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('duplicate')
                        ->label('Duplicate Sale')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->action(function (Sale $record) {
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

                            Notification::make()
                                ->success()
                                ->title('Sale duplicated successfully')
                                ->body("New sale {$newSale->sale_number} created.")
                                ->send();

                            return redirect()->route('filament.admin.resources.sales.edit', $newSale);
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['status' => 'completed']);
                            }
                            Notification::make()
                                ->success()
                                ->title('Sales updated')
                                ->body(count($records) . ' sales marked as completed.')
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('export_receipt')
                        ->label('Export Receipts')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->action(function ($records) {
                            // Implementation for receipt export
                            Notification::make()
                                ->success()
                                ->title('Receipts exported')
                                ->body(count($records) . ' receipts prepared for download.')
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('sale_date', 'desc')
            ->striped()
            ->poll('60s');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SaleItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'view' => Pages\ViewSale::route('/{record}'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['saleItems.product']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['sale_number', 'customer_name', 'customer_phone'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Customer' => $record->customer_name ?? 'Walk-in',
            'Total' => '₱' . number_format($record->total_amount, 2),
            'Items' => $record->saleItems->count() . ' items',
        ];
    }
}