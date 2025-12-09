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
use Filament\Forms\Components\Hidden;


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
                        Forms\Components\DatePicker::make('date_requested')
                            ->label('Date Requested')
                            ->default(now())
                            ->required()
                            ->readOnly(),
                        Forms\Components\DatePicker::make('target_audit_date')
                            ->label('Target Audit Date')
                            ->required()
                            ->minDate(now())
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date_requested')
                    ->label('Date Requested')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_audit_date')
                    ->label('Target Audit Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed At')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                // filter status 
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => auth()->user()->role != 'manager'),
                Tables\Actions\Action::make('audit')
                    ->label('Audit')
                    ->icon('heroicon-o-magnifying-glass')
                    ->visible(fn() => auth()->user()->role === 'manager')
                    ->url(fn (StockAudit $record) =>
                        route('filament.app.resources.stock-audits.audit-products', $record)
                    ),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => auth()->user()->role != 'manager' && $record->status === 'pending')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
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
            'audit-products' => Pages\AuditProducts::route('/{record}/audit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = StockAudit::where('status', 'pending')->count();

        if($count > 0) {
            return "🔍";
        }
        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

}
