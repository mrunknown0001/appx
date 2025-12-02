<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAuditResource\Pages;
use App\Filament\Resources\StockAuditResource\RelationManagers;
use App\Models\StockAudit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;

class StockAuditResource extends Resource
{
    protected static ?string $model = StockAudit::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Misc';

    protected static ?string $navigationLabel = 'Stock Audits';


    protected static ?int $navigationSort = 2;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Stock Audit Details')
                    ->schema([
                        Forms\Components\Select::make('requested_by')
                            ->label('Requested By')
                            ->options(\App\Models\User::query()->pluck('name', 'id'))
                            ->default(auth()->user()->id)
                            ->required()
                            ->disabled(),
                        Forms\Components\DatePicker::make('date_requested')
                            ->label('Date Requested')
                            ->default(now())
                            ->required()
                            ->disabled(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListStockAudits::route('/'),
            'create' => Pages\CreateStockAudit::route('/create'),
            'edit' => Pages\EditStockAudit::route('/{record}/edit'),
        ];
    }
}
