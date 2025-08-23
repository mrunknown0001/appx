<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\Grid;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColorColumn;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Products';

    protected static ?string $navigationGroup = 'Product Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Product Information')
                    ->tabs([
                        Tabs\Tab::make('Basic Information')
                            ->schema([
                                Section::make('Product Details')
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Enter product name')
                                            ->columnSpan(2),

                                        TextInput::make('sku')
                                            ->label('SKU')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->placeholder('e.g., MED-001')
                                            ->helperText('Stock Keeping Unit - must be unique')
                                            ->columnSpan(1),

                                        TextInput::make('barcode')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->placeholder('Product barcode (optional)')
                                            ->columnSpan(1),

                                        Textarea::make('description')
                                            ->placeholder('Product description and notes')
                                            ->rows(3)
                                            ->columnSpanFull(),

                                        Select::make('product_category_id')
                                            ->label('Category')
                                            ->relationship('category', 'name')
                                            ->options(function () {
                                                return ProductCategory::where('is_active', true)
                                                    ->get()
                                                    ->mapWithKeys(function ($category) {
                                                        $indent = str_repeat('— ', $category->getDepth());
                                                        return [$category->id => $indent . $category->name];
                                                    });
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->columnSpan(1),

                                        Select::make('unit_id')
                                            ->label('Unit of Measurement')
                                            ->relationship('unit', 'name')
                                            ->getOptionLabelFromRecordUsing(fn (Unit $record): string => "{$record->name} ({$record->abbreviation})")
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->columnSpan(1),

                                        Grid::make(2)
                                            ->schema([
                                                Toggle::make('is_active')
                                                    ->label('Active')
                                                    ->default(true)
                                                    ->helperText('Inactive products are hidden from sales'),

                                                Toggle::make('is_prescription_required')
                                                    ->label('Prescription Required')
                                                    ->default(false)
                                                    ->helperText('Requires prescription for sale'),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Medical Information')
                            ->schema([
                                Section::make('Pharmaceutical Details')
                                    ->schema([
                                        TextInput::make('generic_name')
                                            ->label('Generic Name')
                                            ->maxLength(255)
                                            ->placeholder('e.g., Paracetamol')
                                            ->columnSpan(1),

                                        TextInput::make('manufacturer')
                                            ->maxLength(255)
                                            ->placeholder('e.g., Pfizer Inc.')
                                            ->columnSpan(1),

                                        TextInput::make('strength')
                                            ->maxLength(255)
                                            ->placeholder('e.g., 500mg, 10mg/ml')
                                            ->helperText('Drug strength and concentration')
                                            ->columnSpan(1),

                                        Select::make('dosage_form')
                                            ->label('Dosage Form')
                                            ->options([
                                                'tablet' => 'Tablet',
                                                'capsule' => 'Capsule',
                                                'syrup' => 'Syrup',
                                                'injection' => 'Injection',
                                                'cream' => 'Cream',
                                                'ointment' => 'Ointment',
                                                'drops' => 'Drops',
                                                'inhaler' => 'Inhaler',
                                                'suspension' => 'Suspension',
                                                'powder' => 'Powder',
                                                'gel' => 'Gel',
                                                'suppository' => 'Suppository',
                                                'patch' => 'Patch',
                                                'solution' => 'Solution',
                                                'other' => 'Other',
                                            ])
                                            ->searchable()
                                            ->placeholder('Select dosage form')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Inventory Settings')
                            ->schema([
                                Section::make('Stock Level Management')
                                    ->schema([
                                        TextInput::make('min_stock_level')
                                            ->label('Minimum Stock Level')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Alert when stock falls below this level')
                                            ->columnSpan(1),

                                        TextInput::make('max_stock_level')
                                            ->label('Maximum Stock Level')
                                            ->numeric()
                                            ->default(1000)
                                            ->minValue(0)
                                            ->helperText('Target maximum inventory level')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(2)
                                    ->description('Set appropriate stock levels for inventory management'),
                            ]),
                    ])
                    ->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->limit(40)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 40 ? $state : null;
                    }),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('SKU copied')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('unit.abbreviation')
                    ->label('Unit')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('generic_name')
                    ->label('Generic')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),

                TextColumn::make('manufacturer')
                    ->searchable()
                    ->limit(25)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('strength')
                    ->toggleable(),

                TextColumn::make('current_stock')
                    ->label('Stock')
                    ->getStateUsing(fn (Product $record): int => $record->getCurrentStock())
                    ->badge()
                    ->color(fn (string $state, Product $record): string => match (true) {
                        (int) $state <= 0 => 'danger',
                        (int) $state <= $record->min_stock_level => 'warning',
                        (int) $state >= $record->max_stock_level => 'info',
                        default => 'success',
                    })
                    ->sortable(),

                TextColumn::make('current_price')
                    ->label('Price')
                    ->getStateUsing(fn (Product $record): string => '₱' . number_format($record->getCurrentPrice(), 2))
                    ->toggleable(),

                IconColumn::make('is_prescription_required')
                    ->label('RX')
                    ->boolean()
                    ->trueIcon('heroicon-o-clipboard-document-list')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (bool $state): string => $state ? 'Prescription Required' : 'No Prescription')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('manufacturer')
                    ->options(function () {
                        return Product::query()
                            ->whereNotNull('manufacturer')
                            ->distinct()
                            ->pluck('manufacturer', 'manufacturer');
                    })
                    ->searchable(),

                SelectFilter::make('dosage_form')
                    ->label('Dosage Form')
                    ->options([
                        'tablet' => 'Tablet',
                        'capsule' => 'Capsule',
                        'syrup' => 'Syrup',
                        'injection' => 'Injection',
                        'cream' => 'Cream',
                        'ointment' => 'Ointment',
                        'drops' => 'Drops',
                        'inhaler' => 'Inhaler',
                        'suspension' => 'Suspension',
                        'powder' => 'Powder',
                        'gel' => 'Gel',
                        'suppository' => 'Suppository',
                        'patch' => 'Patch',
                        'solution' => 'Solution',
                        'other' => 'Other',
                    ]),

                Filter::make('low_stock')
                    ->label('Low Stock')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('inventoryBatches', function ($q) {
                            $q->selectRaw('SUM(current_quantity) as total_stock')
                              ->where('expiry_date', '>', now())
                              ->groupBy('product_id')
                              ->havingRaw('total_stock <= products.min_stock_level');
                        }))
                    ->toggle(),

                Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereDoesntHave('inventoryBatches', function ($q) {
                            $q->where('current_quantity', '>', 0)
                              ->where('expiry_date', '>', now());
                        }))
                    ->toggle(),

                SelectFilter::make('is_prescription_required')
                    ->label('Prescription')
                    ->options([
                        1 => 'Required',
                        0 => 'Not Required',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),

                TrashedFilter::make(),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('stock_history')
                        ->label('Stock History')
                        ->icon('heroicon-o-chart-bar')
                        ->url(fn (Product $record): string => route('filament.app.resources.stock-entries.index', ['tableFilters[product_id][value]' => $record->id]))
                        ->openUrlInNewTab(),
                    Tables\Actions\DeleteAction::make()
                        ->before(function (Product $record) {
                            if ($record->stockEntries()->count() > 0) {
                                throw new \Exception('Cannot delete product that has stock entries.');
                            }
                            if ($record->saleItems()->count() > 0) {
                                throw new \Exception('Cannot delete product that has sales history.');
                            }
                        }),
                    Tables\Actions\RestoreAction::make(),
                    Tables\Actions\ForceDeleteAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->stockEntries()->count() > 0 || $record->saleItems()->count() > 0) {
                                    throw new \Exception('Cannot delete products that have stock entries or sales history.');
                                }
                            }
                        }),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => true]);
                            }
                            Notification::make()
                                ->success()
                                ->title('Products activated')
                                ->body(count($records) . ' products have been activated.')
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => false]);
                            }
                            Notification::make()
                                ->success()
                                ->title('Products deactivated')
                                ->body(count($records) . ' products have been deactivated.')
                                ->send();
                        }),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->striped()
            ->poll('60s');
    }

    public static function getRelations(): array
    {
        return [
            // You can add relation managers here for stock entries, price history, etc.
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'view' => Pages\ViewProduct::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['category', 'unit']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'barcode', 'generic_name', 'manufacturer'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'SKU' => $record->sku,
            'Category' => $record->category?->name,
            'Manufacturer' => $record->manufacturer,
        ];
    }
}