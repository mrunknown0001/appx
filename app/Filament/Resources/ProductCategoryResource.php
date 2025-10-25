<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductCategoryResource\Pages;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Exceptions\Halt;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Product Categories';

    protected static ?string $modelLabel = 'Product Category';

    protected static ?string $pluralModelLabel = 'Product Categories';

    protected static ?string $navigationGroup = 'Product Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Category Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter category name')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->placeholder('Enter category description (optional)')
                            ->rows(3)
                            ->columnSpanFull(),

                        Select::make('parent_id')
                            ->label('Parent Category')
                            ->placeholder('Select parent category (optional)')
                            ->options(function () {
                                return ProductCategory::where('is_active', true)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to create a main category')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, $livewire) {
                                // Prevent self-reference when editing
                                if (isset($livewire->record) && $state == $livewire->record->id) {
                                    $set('parent_id', null);
                                }
                            })
                            ->columnSpan(1),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive categories will be hidden from product selection')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
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
                    ->label('Category Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(function (string $state, ProductCategory $record): string {
                        // Add indentation for subcategories
                        $indent = str_repeat('— ', $record->getDepth());
                        return $indent . $state;
                    }),

                TextColumn::make('parent.name')
                    ->label('Parent Category')
                    ->sortable()
                    ->searchable()
                    ->placeholder('Main Category')
                    ->toggleable(),

                TextColumn::make('children_count')
                    ->label('Subcategories')
                    ->counts('children')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable()
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
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('recent')
                    ->label('Recently Created (7 days)')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('created_at', '>=', now()->subDays(7))
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('this_month')
                    ->label('Created This Month')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereMonth('created_at', now()->month)
                            ->whereYear('created_at', now()->year)
                    )
                    ->toggle(),

                SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->options([
                        '' => 'Main Categories Only',
                        ...ProductCategory::whereNull('parent_id')
                            ->where('is_active', true)
                            ->pluck('name', 'id')
                            ->toArray()
                    ])
                    ->placeholder('All Categories'),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ])
                    ->placeholder('All Statuses'),

                TrashedFilter::make(),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->modalHeading('Delete Category')
                        ->form(function (ProductCategory $record) {
                            // Check for subcategories first
                            if ($record->children()->count() > 0) {
                                return [
                                    Forms\Components\Placeholder::make('error')
                                        ->content('This category has subcategories and cannot be deleted. Please delete or move subcategories first.')
                                        ->extraAttributes(['class' => 'text-danger-600']),
                                ];
                            }
                            
                            // Check for products
                            $productCount = $record->products()->count();
                            
                            if ($productCount > 0) {
                                return [
                                    Forms\Components\Section::make()
                                        ->heading("This category has {$productCount} product(s)")
                                        ->description('Choose how to handle the products before deleting this category')
                                        ->schema([
                                            Forms\Components\Radio::make('products_action')
                                                ->label('What should we do with the products?')
                                                ->options([
                                                    'move' => 'Move products to another category',
                                                    'delete' => 'Delete all products',
                                                ])
                                                ->default('move')
                                                ->required()
                                                ->live(),
                                            
                                            Forms\Components\Select::make('target_category_id')
                                                ->label('Move products to')
                                                ->options(function (ProductCategory $record) {
                                                    return ProductCategory::where('is_active', true)
                                                        ->where('id', '!=', $record->id)
                                                        ->pluck('name', 'id');
                                                })
                                                ->searchable()
                                                ->required()
                                                ->visible(fn ($get) => $get('products_action') === 'move')
                                                ->placeholder('Select a category'),
                                            
                                            Forms\Components\Placeholder::make('warning')
                                                ->content('⚠️ All products in this category will be permanently deleted!')
                                                ->visible(fn ($get) => $get('products_action') === 'delete'),
                                        ]),
                                ];
                            }
                            
                            return [];
                        })
                        ->action(function (ProductCategory $record, array $data) {
                            // Prevent deletion if has subcategories
                            if ($record->children()->count() > 0) {
                                Notification::make()
                                    ->danger()
                                    ->title('Cannot Delete')
                                    ->body('This category has subcategories.')
                                    ->send();
                                return;
                            }
                            
                            // Handle products
                            if ($record->products()->count() > 0) {
                                if (isset($data['products_action'])) {
                                    if ($data['products_action'] === 'move' && isset($data['target_category_id'])) {
                                        $movedCount = $record->products()->count();
                                        $record->products()->update(['product_category_id' => $data['target_category_id']]);
                                        
                                        Notification::make()
                                            ->success()
                                            ->title("{$movedCount} product(s) moved")
                                            ->send();
                                    } elseif ($data['products_action'] === 'delete') {
                                        $deletedCount = $record->products()->count();

                                        // check a product has stock entries or sales record
                                        foreach($record->products as $product) {
                                            if($product->stockEntries()->count() > 0 || $product->saleItems()->count() > 0) {
                                                Notification::make()
                                                    ->warning()
                                                    ->title("Products unable to delete.")
                                                    ->body("{$product->name} products has Stock Entry or Sales Record")
                                                    ->send();
                                                return;
                                            }
                                        }

                                        $record->products()->delete();
                                        
                                        Notification::make()
                                            ->warning()
                                            ->title("{$deletedCount} product(s) deleted")
                                            ->send();
                                    }
                                }
                            }
                            
                            // Delete the category
                            $record->delete();
                            
                            Notification::make()
                                ->success()
                                ->title('Category Deleted')
                                ->body('The category has been successfully deleted.')
                                ->send();
                        })
                        ->requiresConfirmation(),
                    Tables\Actions\RestoreAction::make(),
                    Tables\Actions\ForceDeleteAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->products()->count() > 0 || $record->children()->count() > 0) {
                                    throw new \Exception('Cannot delete categories that have products or subcategories.');
                                }
                            }
                        }),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->poll('60s');
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
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'view' => Pages\ViewProductCategory::route('/{record}'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with([
                'parent',
                'children',
                'products',
                'products.unit',
            ])
            ->withCount(['children', 'products']);
    }

}