<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryBatchResource\Pages;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\StockEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\ActionGroup;
use Carbon\Carbon;

class InventoryBatchResource extends Resource
{
    protected static ?string $model = InventoryBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?string $navigationGroup = 'Inventory Management';

    protected static ?string $navigationLabel = 'Inventory Batches';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Batch Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('location', 'Main Storage');
                                            }
                                        }
                                    })
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('sku')
                                            ->label('SKU')
                                            ->required()
                                            ->unique(Product::class, 'sku')
                                            ->maxLength(100),
                                    ]),

                                Forms\Components\Select::make('stock_entry_id')
                                    ->label('Stock Entry')
                                    ->relationship('stockEntry', 'invoice_number')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        return "{$record->invoice_number} - {$record->supplier_name} ({$record->entry_date->format('M d, Y')})";
                                    }),

                                Forms\Components\TextInput::make('batch_number')
                                    ->label('Batch Number')
                                    ->maxLength(255)
                                    ->placeholder('Auto-generated if empty')
                                    ->helperText('Leave empty to auto-generate'),

                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Active',
                                        'expired' => 'Expired',
                                        'out_of_stock' => 'Out of Stock',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Quantity Management')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('initial_quantity')
                                    ->label('Initial Quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state && !$get('current_quantity')) {
                                            $set('current_quantity', $state);
                                        }
                                    })
                                    ->suffix(function (callable $get) {
                                        $productId = $get('product_id');
                                        if ($productId) {
                                            $product = Product::find($productId);
                                            return $product?->unit?->abbreviation ?? 'units';
                                        }
                                        return 'units';
                                    }),

                                Forms\Components\TextInput::make('current_quantity')
                                    ->label('Current Quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->suffix(function (callable $get) {
                                        $productId = $get('product_id');
                                        if ($productId) {
                                            $product = Product::find($productId);
                                            return $product?->unit?->abbreviation ?? 'units';
                                        }
                                        return 'units';
                                    }),
                            ]),
                    ]),

                Section::make('Storage & Expiry')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('expiry_date')
                                    ->label('Expiry Date')
                                    ->required()
                                    ->minDate(now())
                                    ->displayFormat('M d, Y')
                                    ->helperText('Select the expiry date for this batch'),

                                Forms\Components\TextInput::make('location')
                                    ->label('Storage Location')
                                    ->maxLength(255)
                                    ->default('Main Storage')
                                    ->placeholder('e.g., Warehouse A, Section 1'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Batch #')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->placeholder('No batch number')
                    ->copyable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(function ($record) {
                        return "SKU: {$record->product->sku}";
                    }),

                Tables\Columns\TextColumn::make('stockEntry.supplier_name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Current Stock')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        $unit = $record->product?->unit?->abbreviation ?? 'units';
                        return number_format($record->current_quantity) . ' ' . $unit;
                    })
                    ->color(function ($record) {
                        $product = $record->product;
                        if ($product && $record->current_quantity <= $product->min_stock_level) {
                            return 'danger';
                        }
                        return $record->current_quantity > 0 ? 'success' : 'gray';
                    }),

                Tables\Columns\TextColumn::make('initial_quantity')
                    ->label('Initial')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        $unit = $record->product?->unit?->abbreviation ?? 'units';
                        return number_format($record->initial_quantity) . ' ' . $unit;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('usage_percentage')
                    ->label('Usage %')
                    ->state(function ($record) {
                        if ($record->initial_quantity <= 0) return 0;
                        $used = $record->initial_quantity - $record->current_quantity;
                        return round(($used / $record->initial_quantity) * 100, 1);
                    })
                    ->suffix('%')
                    ->color(function ($state) {
                        if ($state >= 80) return 'danger';
                        if ($state >= 60) return 'warning';
                        return 'success';
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry Date')
                    ->date('M d, Y')
                    ->sortable()
                    ->color(function ($record) {
                        $daysUntilExpiry = $record->expiry_date->diffInDays(now(), false);
                        if ($daysUntilExpiry > 0) return 'danger'; // Expired
                        if ($daysUntilExpiry > -30) return 'warning'; // Expires soon
                        return 'success';
                    })
                    ->description(function ($record) {
                        $daysUntilExpiry = $record->expiry_date->diffInDays(now(), false);
                        if ($daysUntilExpiry > 0) {
                            return "Expired " . $record->expiry_date->diffForHumans();
                        }
                        return "Expires " . $record->expiry_date->diffForHumans();
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'expired',
                        'danger' => 'out_of_stock',
                    ])
                    ->formatStateUsing(function ($state) {
                        return ucfirst(str_replace('_', ' ', $state));
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->placeholder('Not specified')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'out_of_stock' => 'Out of Stock',
                    ])
                    ->multiple(),

                Filter::make('expiry_status')
                    ->label('Expiry Status')
                    ->query(function (Builder $query, array $data) {
                        if ($data['expired'] ?? false) {
                            $query->where('expiry_date', '<', now());
                        }
                        if ($data['expires_soon'] ?? false) {
                            $query->where('expiry_date', '>=', now())
                                  ->where('expiry_date', '<=', now()->addDays(30));
                        }
                        if ($data['expires_later'] ?? false) {
                            $query->where('expiry_date', '>', now()->addDays(30));
                        }
                        return $query;
                    })
                    ->form([
                        Forms\Components\Checkbox::make('expired')
                            ->label('Expired'),
                        Forms\Components\Checkbox::make('expires_soon')
                            ->label('Expires Soon (30 days)'),
                        Forms\Components\Checkbox::make('expires_later')
                            ->label('Expires Later (30+ days)'),
                    ]),

                Filter::make('stock_level')
                    ->label('Stock Level')
                    ->query(function (Builder $query, array $data) {
                        if ($data['out_of_stock'] ?? false) {
                            $query->where('current_quantity', '<=', 0);
                        }
                        if ($data['low_stock'] ?? false) {
                            $query->whereHas('product', function ($q) {
                                $q->whereRaw('inventory_batches.current_quantity <= products.min_stock_level');
                            });
                        }
                        if ($data['in_stock'] ?? false) {
                            $query->where('current_quantity', '>', 0);
                        }
                        return $query;
                    })
                    ->form([
                        Forms\Components\Checkbox::make('out_of_stock')
                            ->label('Out of Stock'),
                        Forms\Components\Checkbox::make('low_stock')
                            ->label('Low Stock'),
                        Forms\Components\Checkbox::make('in_stock')
                            ->label('In Stock'),
                    ]),

                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('location')
                    ->label('Location')
                    ->options(function () {
                        return InventoryBatch::distinct()
                            ->whereNotNull('location')
                            ->pluck('location', 'location')
                            ->toArray();
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    // Tables\Actions\EditAction::make(),
                    
                    Tables\Actions\Action::make('adjust_quantity')
                        ->label('Adjust Quantity')
                        ->icon('heroicon-o-calculator')
                        ->color('warning')
                        ->form([
                            Forms\Components\TextInput::make('adjustment')
                                ->label('Quantity Adjustment')
                                ->numeric()
                                ->required()
                                ->helperText('Use negative numbers to reduce quantity, positive to increase')
                                ->placeholder('e.g., -10 or +5'),
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for Adjustment')
                                ->placeholder('e.g., Damaged goods, Found extra stock, etc.')
                                ->required(),
                        ])
                        ->action(function (InventoryBatch $record, array $data) {
                            $newQuantity = $record->current_quantity + $data['adjustment'];
                            
                            if ($newQuantity < 0) {
                                Notification::make()
                                    ->danger()
                                    ->title('Invalid Adjustment')
                                    ->body('Adjustment would result in negative quantity.')
                                    ->send();
                                return;
                            }

                            $record->update([
                                'current_quantity' => $newQuantity,
                                'status' => $newQuantity == 0 ? 'out_of_stock' : 'active',
                            ]);

                            // Log the adjustment (you might want to create an audit log)
                            
                            Notification::make()
                                ->success()
                                ->title('Quantity Adjusted')
                                ->body("Batch quantity adjusted by {$data['adjustment']}. Reason: {$data['reason']}")
                                ->send();
                        }),

                    Tables\Actions\Action::make('mark_expired')
                        ->label('Mark as Expired')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Batch as Expired')
                        ->modalDescription('Are you sure you want to mark this batch as expired? This action cannot be undone.')
                        ->action(function (InventoryBatch $record) {
                            $record->update(['status' => 'expired']);
                            
                            Notification::make()
                                ->warning()
                                ->title('Batch Marked as Expired')
                                ->body("Batch {$record->batch_number} has been marked as expired.")
                                ->send();
                        })
                        ->visible(fn (InventoryBatch $record) => $record->status !== 'expired'),

                    Tables\Actions\Action::make('view_product')
                        ->label('View Product')
                        ->icon('heroicon-o-cube')
                        ->url(fn (InventoryBatch $record): string => 
                            route('filament.app.resources.products.view', $record->product)
                        )
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('view_stock_entry')
                        ->label('View Stock Entry')
                        ->icon('heroicon-o-document-text')
                        ->url(fn (InventoryBatch $record): string => 
                            route('filament.app.resources.stock-entries.view', $record->stockEntry)
                        )
                        ->openUrlInNewTab(),

                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Inventory Batch')
                        ->modalDescription('Are you sure you want to delete this inventory batch? This action cannot be undone and may affect related records.')
                        ->before(function (InventoryBatch $record) {
                            // Check if this batch has been used in sales
                            if ($record->saleItems()->exists()) {
                                throw new \Exception('Cannot delete inventory batch that has been used in sales.');
                            }
                        }),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_expired')
                        ->label('Mark as Expired')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'expired') {
                                    $record->update(['status' => 'expired']);
                                    $count++;
                                }
                            }
                            
                            Notification::make()
                                ->warning()
                                ->title('Batches Marked as Expired')
                                ->body("{$count} batches have been marked as expired.")
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->saleItems()->exists()) {
                                    throw new \Exception('Cannot delete inventory batches that have been used in sales.');
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryBatches::route('/'),
            'create' => Pages\CreateInventoryBatch::route('/create'),
            'view' => Pages\ViewInventoryBatch::route('/{record}'),
            'edit' => Pages\EditInventoryBatch::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['product.unit', 'stockEntry'])
            ->orderBy('created_at', 'desc');
    }
}