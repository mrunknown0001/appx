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
use Filament\Support\Enums\FontWeight;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Product Categories';

    protected static ?string $modelLabel = 'Product Category';

    protected static ?string $pluralModelLabel = 'Product Categories';

    protected static ?string $navigationGroup = 'Product Management';

    protected static ?int $navigationSort = 1;

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
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
                        ->before(function (ProductCategory $record) {
                            // Check if category has products or subcategories
                            if ($record->products()->count() > 0) {
                                throw new \Exception('Cannot delete category that has products assigned to it.');
                            }
                            if ($record->children()->count() > 0) {
                                throw new \Exception('Cannot delete category that has subcategories.');
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
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['parent', 'children'])
            ->withCount(['children', 'products']);
    }

}