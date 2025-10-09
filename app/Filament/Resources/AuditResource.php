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
use Illuminate\Support\Str;


class AuditResource extends Resource
{
    protected static ?string $model = \OwenIt\Auditing\Models\Audit::class;

    protected static ?string $navigationGroup = 'Misc';

    protected static ?string $navigationLabel = 'Audit Logs';

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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime('M j, Y g:i A')
                    ->description(fn ($record) => optional($record->created_at)?->diffForHumans())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Performed By')
                    ->icon('heroicon-o-user')
                    ->formatStateUsing(fn ($state, $record) => $record->user?->name ?? 'System')
                    ->description(fn ($record) => $record->user_id ? "ID: {$record->user_id}" : null)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search) {
                            $query
                                ->where('user_id', 'like', "%{$search}%")
                                ->orWhereHas('user', function (Builder $relationQuery) use ($search) {
                                    $relationQuery->where('name', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? Str::headline($state) : 'Unknown')
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—')
                    ->description(fn ($record) => $record->auditable_id ? "#{$record->auditable_id}" : null)
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('old_values')
                    ->label('Before')
                    ->formatStateUsing(fn (?string $state) => self::formatAuditValues($state))
                    ->extraAttributes(['class' => 'whitespace-pre-wrap text-xs'])
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                Tables\Columns\TextColumn::make('new_values')
                    ->label('After')
                    ->formatStateUsing(fn (?string $state) => self::formatAuditValues($state))
                    ->extraAttributes(['class' => 'whitespace-pre-wrap text-xs'])
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // filter employee id
                // Tables\Filters\SelectFilter::make('user_id')
                //     ->label('Employee ID')
                //     ->options(function () {
                //         return DB::table('users')->pluck('employee_id', 'id')->toArray();
                //     })->searchable(),

                // date filter
                Tables\Filters\Filter::make('created_at')
                    ->label('Date')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created Until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
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
    protected static function formatAuditValues(?string $json): string
    {
        if (blank($json)) {
            return '—';
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || $decoded === []) {
            return '—';
        }

        return collect($decoded)
            ->map(function ($value, $key) {
                $label = Str::headline((string) $key);
                $stringValue = self::stringifyAuditValue($value);

                return "{$label}: {$stringValue}";
            })
            ->implode(PHP_EOL);
    }

    protected static function stringifyAuditValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
