<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockEntryResource\Pages;
use App\Models\StockEntry;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;
use Filament\Forms\Get;
use Filament\Forms\Set;

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
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(2)
                                    ->getOptionLabelFromRecordUsing(fn (Product $record): string => 
                                        "{$record->name} ({$record->sku})"
                                    ),

                                TextInput::make('supplier_name')
                                    ->label('Supplier Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Enter supplier name'),

                                TextInput::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->maxLength(255)
                                    ->placeholder('Enter invoice number')
                                    ->helperText('Optional reference number'),

                                DatePicker::make('entry_date')
                                    ->label('Entry Date')
                                    ->required()
                                    ->default(now())
                                    ->maxDate(now()),

                                DatePicker::make('expiry_date')
                                    ->label('Expiry Date')
                                    ->required()
                                    ->after('entry_date')
                                    ->helperText('Must be after entry date'),
                            ])
                            ->columns(2),
                    ]),

                Section::make('Quantity & Pricing')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('quantity_received')
                                    ->label('Quantity Received')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        $unitCost = $get('unit_cost');
                                        if ($state && $unitCost) {
                                            $set('total_cost', $state * $unitCost);
                                        }
                                    }),

                                TextInput::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₱')
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        $quantity = $get('quantity_received');
                                        if ($state && $quantity) {
                                            $set('total_cost', $quantity * $state);
                                        }
                                    }),

                                TextInput::make('total_cost')
                                    ->label('Total Cost')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₱')
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->readOnly()
                                    ->helperText('Automatically calculated'),
                            ])
                            ->columns(3),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('batch_number')
                                    ->label('Batch Number')
                                    ->maxLength(255)
                                    ->placeholder('Enter batch number')
                                    ->helperText('Optional batch identifier'),

                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(3)
                                    ->placeholder('Additional notes about this stock entry')
                                    ->columnSpan(1),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->description(fn (StockEntry $record): string => 
                        "SKU: {$record->product->sku}"
                    ),

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

                TextColumn::make('quantity_received')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->badge()
                    ->color('success'),

                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd()
                    ->weight(FontWeight::Bold),

                TextColumn::make('expiry_date')
                    ->label('Expiry Date')
                    ->date()
                    ->sortable()
                    ->badge()
                    ->color(fn (StockEntry $record): string => 
                        $record->expiry_date->isPast() ? 'danger' : 
                        ($record->expiry_date->diffInDays() <= 30 ? 'warning' : 'success')
                    )
                    ->formatStateUsing(fn (StockEntry $record): string => 
                        $record->expiry_date->format('M d, Y') . 
                        ($record->expiry_date->isPast() ? ' (Expired)' : 
                        ($record->expiry_date->diffInDays() <= 30 ? ' (Expires Soon)' : ''))
                    ),

                TextColumn::make('batch_number')
                    ->label('Batch')
                    ->searchable()
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

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
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['expired'] ?? false) {
                            $query->where('expiry_date', '<', now());
                        }
                        if ($data['expires_soon'] ?? false) {
                            $query->where('expiry_date', '>=', now())
                                  ->where('expiry_date', '<=', now()->addDays(30));
                        }
                        return $query;
                    })
                    ->form([
                        Forms\Components\Checkbox::make('expired')
                            ->label('Show Expired'),
                        Forms\Components\Checkbox::make('expires_soon')
                            ->label('Show Expiring Soon (30 days)'),
                    ]),

                SelectFilter::make('supplier_name')
                    ->label('Supplier')
                    ->options(function () {
                        return StockEntry::distinct()
                            ->pluck('supplier_name', 'supplier_name')
                            ->toArray();
                    })
                    ->searchable(),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('view_product')
                        ->label('View Product')
                        ->icon('heroicon-o-cube')
                        ->url(fn (StockEntry $record): string => 
                            route('filament.app.resources.products.view', $record->product)
                        )
                        ->openUrlInNewTab(),
                    Tables\Actions\DeleteAction::make()
                        ->before(function (StockEntry $record) {
                            // Check if this stock entry has associated inventory batches
                            if ($record->inventoryBatch()->exists()) {
                                throw new \Exception('Cannot delete stock entry that has associated inventory batches.');
                            }
                        }),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->inventoryBatch()->exists()) {
                                    throw new \Exception('Cannot delete stock entries that have associated inventory batches.');
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->poll('60s');
    }

    public static function getRelations(): array
    {
        return [
            // You can add relation managers here for inventory batches, etc.
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
            ->with(['product', 'product.category', 'product.unit']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['supplier_name', 'invoice_number', 'batch_number', 'product.name', 'product.sku'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Product' => $record->product->name,
            'Supplier' => $record->supplier_name,
            'Invoice' => $record->invoice_number ?? 'N/A',
            'Entry Date' => $record->entry_date->format('M d, Y'),
        ];
    }
}