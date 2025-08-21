<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditResource\Pages;
use App\Filament\Resources\AuditResource\RelationManagers;
use App\Models\Audit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;


class AuditResource extends Resource
{
    protected static ?string $model = \OwenIt\Auditing\Models\Audit::class;

    protected static ?string $navigationGroup = 'Misc';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->label('Employee ID'),
                Tables\Columns\TextColumn::make('auditable_type'),
                Tables\Columns\TextColumn::make('auditable_id'),
                Tables\Columns\TextColumn::make('event'),
                Tables\Columns\TextColumn::make('old_values'),
                Tables\Columns\TextColumn::make('new_values'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('F j, Y, g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

            ])
            ->filters([
                //
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAudits::route('/'),
            'create' => Pages\CreateAudit::route('/create'),
            'edit' => Pages\EditAudit::route('/{record}/edit'),
        ];
    }
}
