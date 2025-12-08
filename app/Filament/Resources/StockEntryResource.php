<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockEntryResource\Pages;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StockEntryResource extends Resource
{
    protected static ?string $model = StockEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationLabel = 'Stock Entries';

    protected static ?string $modelLabel = 'Stock Entry';

    protected static ?string $pluralModelLabel = 'Stock Entries';

    protected static ?string $navigationGroup = 'Inventory Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Stock Entry Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('supplier_name')
                                    ->label('Supplier')
                                    ->required()
                                    ->searchable()
                                    ->placeholder('Select supplier')
                                    ->options(fn () => Supplier::orderBy('name')->pluck('name', 'name'))
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Supplier Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true),
                                        Forms\Components\TextInput::make('short_code')
                                            ->label('Short Code')
                                            ->maxLength(50),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        return Supplier::create([
                                            'name' => $data['name'],
                                        ]);
                                    }),

                                TextInput::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->maxLength(255)
                                    ->placeholder('Enter invoice number')
                                    ->helperText('Optional reference number'),

                                DatePicker::make('entry_date')
                                    ->label('Entry Date')
                                    ->required()
                                    ->default(now())
                                    ->maxDate(now())
                                    ->columnSpan(1),

                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->placeholder('Additional notes about this stock entry')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Products & Quantities')
                    ->schema([
                        Repeater::make('items')
                            ->label('Products')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => $state['product_label'] ?? null)
                            ->schema([
                                Grid::make()
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Product')
                                            ->relationship('product', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->columnSpan(3)
                                            ->getOptionLabelFromRecordUsing(fn (Product $record): string => "{$record->name} ({$record->sku})")
                                            ->afterStateHydrated(function (Set $set, ?int $state, ?array $record) {
                                                if ($record && isset($record['product'])) {
                                                    $product = $record['product'];
                                                    $set('product_label', "{$product['name']} ({$product['sku']})");
                                                }
                                            })
                                            ->afterStateUpdated(function (Set $set, ?int $state) {
                                                if (!$state) {
                                                    $set('product_label', null);
                                                    return;
                                                }

                                                $product = Product::find($state);
                                                if ($product) {
                                                    $set('product_label', "{$product->name} ({$product->sku})");
                                                }
                                            }),

                                        TextInput::make('quantity_received')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->minValue(1)
                                            ->required()
                                            ->columnSpan(1)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                $unitCost = (float) $get('unit_cost');
                                                $quantity = (int) $state;
                                                $set('total_cost', $quantity && $unitCost ? round($quantity * $unitCost, 4) : null);
                                            }),

                                        TextInput::make('unit_cost')
                                            ->label('Unit Cost')
                                            ->numeric()
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->prefix('₱')
                                            ->required()
                                            ->columnSpan(1)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                $unitCost = (float) $state;
                                                $quantity = (int) $get('quantity_received');
                                                $set('total_cost', $quantity && $unitCost ? round($quantity * $unitCost, 4) : null);
                                            }),

                                        TextInput::make('selling_price')
                                            ->label('Selling Price')
                                            ->numeric()
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->prefix('₱')
                                            ->required()
                                            ->columnSpan(1),

                                        TextInput::make('total_cost')
                                            ->label('Total Cost')
                                            ->numeric()
                                            ->step(0.01)
                                            ->prefix('₱')
                                            ->readOnly()
                                            ->dehydrated(true)
                                            ->columnSpan(1),

                                        DatePicker::make('expiry_date')
                                            ->label('Expiry Date')
                                            ->nullable()
                                            ->after('entry_date')
                                            ->required()
                                            ->columnSpan(1),

                                        TextInput::make('batch_number')
                                            ->label('Batch Number')
                                            ->maxLength(255)
                                            ->required()
                                            ->placeholder('Batch Indentifier')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(6),

                                Textarea::make('notes')
                                    ->label('Item Notes')
                                    ->rows(2)
                                    ->columnSpanFull()
                                    ->placeholder('Optional item-specific notes'),
                            ])
                            ->columns(1),
                    ]),
                Section::make('Totals')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('items_count_display')
                                    ->label('Number of Products')
                                    ->content(fn (Get $get): string => (string) collect($get('items') ?? [])->count())
                                    ->extraAttributes(['class' => 'text-lg font-semibold']),

                                Placeholder::make('total_quantity_display')
                                    ->label('Total Quantity')
                                    ->content(fn (Get $get): string => (string) collect($get('items') ?? [])->sum(fn ($item) => (int) ($item['quantity_received'] ?? 0)))
                                    ->extraAttributes(['class' => 'text-lg font-semibold']),

                                Placeholder::make('total_cost_display')
                                    ->label('Total Cost')
                                    ->content(function (Get $get): string {
                                        $total = collect($get('items') ?? [])->sum(function ($item) {
                                            $qty = (int) ($item['quantity_received'] ?? 0);
                                            $unit = (float) ($item['unit_cost'] ?? 0);
                                            return $qty && $unit ? $qty * $unit : (float) ($item['total_cost'] ?? 0);
                                        });

                                        return '₱' . number_format($total, 2);
                                    })
                                    ->extraAttributes(['class' => 'text-lg font-semibold text-success-600 dark:text-success-400']),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier_name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->placeholder('N/A')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('entry_date')
                    ->label('Entry Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('items_summary')
                    ->label('Products')
                    ->state(function (StockEntry $record): string {
                        return $record->items
                            ->map(function ($item) {
                                $productName = $item->product?->name ?? 'Unknown product';
                                $sku = $item->product?->sku ? " ({$item->product->sku})" : '';
                                $quantity = number_format($item->quantity_received ?? 0);
                                $unit = $item->product?->unit?->abbreviation ?? 'units';

                                return "{$productName}{$sku}<br><span class=\"text-gray-500\">Qty: {$quantity} {$unit} · ₱" .
                                    number_format((float) $item->unit_cost, 2) . '</span>';
                            })
                            ->implode('<br>');
                    })
                    ->html()
                    ->limit(200)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->numeric()
                    ->badge()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('PHP')
                    ->alignEnd()
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product')
                    ->label('Product')
                    ->options(fn () => Product::orderBy('name')->pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('items', fn (Builder $q) => $q->where('product_id', $data['value']));
                    }),

                Filter::make('entry_date')
                    ->form([
                        DatePicker::make('entry_from')
                            ->label('Entry Date From'),
                        DatePicker::make('entry_until')
                            ->label('Entry Date Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['entry_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('entry_date', '>=', $date),
                            )
                            ->when(
                                $data['entry_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('entry_date', '<=', $date),
                            );
                    }),

                Filter::make('expiry_status')
                    ->label('Expiry Status')
                    ->form([
                        Forms\Components\Checkbox::make('expired')
                            ->label('Show Expired'),
                        Forms\Components\Checkbox::make('expires_soon')
                            ->label('Show Expiring Soon (30 days)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['expired'] ?? false,
                                fn (Builder $query) => $query->whereHas('items', fn (Builder $q) => $q->where('expiry_date', '<', now()))
                            )
                            ->when(
                                $data['expires_soon'] ?? false,
                                fn (Builder $query) => $query->whereHas('items', fn (Builder $q) => $q
                                    ->where('expiry_date', '>=', now())
                                    ->where('expiry_date', '<=', now()->addDays(30)))
                            );
                    }),

                SelectFilter::make('supplier_name')
                    ->label('Supplier')
                    ->options(fn () => StockEntry::query()
                        ->distinct()
                        ->pluck('supplier_name', 'supplier_name')
                        ->filter()
                        ->toArray())
                    ->searchable(),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->before(function (StockEntry $record) {
                            if ($record->inventoryBatches()->exists()) {
                                throw new \Exception('Cannot delete stock entry that has associated inventory batches.');
                            }
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Collection $records) {
                            foreach ($records as $record) {
                                if ($record->inventoryBatches()->exists()) {
                                    throw new \Exception('Cannot delete stock entries that have associated inventory batches.');
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('entry_date', 'desc')
            ->striped()
            ->poll('60s');
    }

    public static function getRelations(): array
    {
        return [
            // Relation managers can be added here for stock entry items or inventory batches when needed.
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockEntries::route('/'),
            'create' => Pages\CreateStockEntry::route('/create'),
            'edit' => Pages\EditStockEntry::route('/{record}/edit'),
            'view' => Pages\ViewStockEntry::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['items.product.unit', 'inventoryBatches']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'supplier_name',
            'invoice_number',
            'items.batch_number',
            'items.product.name',
            'items.product.sku',
        ];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        $productSummary = $record->items
            ->take(3)
            ->map(fn ($item) => $item->product?->name ?? 'Unknown product')
            ->implode(', ');

        return [
            'Supplier' => $record->supplier_name,
            'Products' => $productSummary ?: 'No products',
            'Total Quantity' => number_format($record->total_quantity ?? 0),
            'Entry Date' => optional($record->entry_date)->format('M d, Y') ?? 'N/A',
        ];
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['manager', 'superadmin']);
    }
}