<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Support\Enums\FontWeight;
use Illuminate\Validation\Rule;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Units';

    protected static ?string $modelLabel = 'Unit';

    protected static ?string $pluralModelLabel = 'Units';

    protected static ?string $navigationGroup = 'Product Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Unit Information')
                    ->description('Define units of measurement for your products')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Pieces, Bottles, Strips')
                            ->helperText('Enter the full name of the unit')
                            ->columnSpan(2),

                        TextInput::make('abbreviation')
                            ->required()
                            ->maxLength(10)
                            ->placeholder('e.g., pcs, btl, strip')
                            ->helperText('Short form (max 10 characters)')
                            ->unique(ignoreRecord: true)
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        if (preg_match('/[^a-zA-Z0-9]/', $value)) {
                                            $fail('The abbreviation may only contain letters and numbers.');
                                        }
                                    };
                                },
                            ])
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description')
                            ->placeholder('Optional description or additional notes about this unit')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
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
                    ->label('Unit Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->copyable()
                    ->copyMessage('Unit name copied'),

                TextColumn::make('abbreviation')
                    ->label('Abbreviation')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->copyable()
                    ->copyMessage('Abbreviation copied'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    })
                    ->toggleable(),

                TextColumn::make('products_count')
                    ->label('Products Using')
                    ->counts('products')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state === '0' => 'gray',
                        (int) $state < 10 => 'warning',
                        default => 'success',
                    })
                    ->toggleable(),

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
                Filter::make('has_products')
                    ->label('Has Products')
                    ->query(fn (Builder $query): Builder => $query->has('products'))
                    ->toggle(),

                Filter::make('no_products')
                    ->label('No Products')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('products'))
                    ->toggle(),

                Filter::make('recent')
                    ->label('Recently Added')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(7)))
                    ->toggle(),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->before(function (Unit $record) {
                            if ($record->products()->count() > 0) {
                                throw new \Exception('Cannot delete unit that is assigned to products. Please reassign products to different units first.');
                            }
                        })
                        ->successNotification(
                            fn () => \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Unit deleted')
                                ->body('The unit has been deleted successfully.')
                        ),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->products()->count() > 0) {
                                    throw new \Exception('Cannot delete units that are assigned to products.');
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('name')
            ->poll('60s')
            ->searchOnBlur();
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
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('products');
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['products']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'abbreviation', 'description'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Abbreviation' => $record->abbreviation,
            'Products' => $record->products_count . ' products',
        ];
    }
}