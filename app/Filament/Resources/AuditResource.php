<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditResource\Pages;
use App\Filament\Resources\AuditResource\RelationManagers;
use App\Models\Audit;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
                Tables\Columns\TextColumn::make('performed_by')
                    ->label('Performed By')
                    ->icon('heroicon-o-user')
                    ->state(fn ($record) => self::resolveActorLabel($record))
                    ->description(function ($record) {
                        if ($record->user_id) {
                            return "ID: {$record->user_id}";
                        }

                        if (self::isLoginAudit($record) && $record->auditable_id) {
                            return "User ID: {$record->auditable_id}";
                        }

                        return null;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search) {
                            $query
                                ->where('user_id', 'like', "%{$search}%")
                                ->orWhereHas('user', function (Builder $relationQuery) use ($search) {
                                    $relationQuery->where('name', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, $record) => self::resolveEventLabel($record))
                    ->color(fn (?string $state, $record) => self::resolveEventColor($record))
                    ->description(function ($record) {
                        $subjectType = $record->auditable_type ? class_basename($record->auditable_type) : 'Record';
                        $label = self::resolveAuditableLabel($record);

                        if ($label) {
                            return "Action performed on {$subjectType}: {$label}";
                        }

                        if ($record->auditable_id) {
                            return "Action performed on {$subjectType} #{$record->auditable_id}";
                        }

                        return "Action performed on {$subjectType}";
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—')
                    ->description(fn ($record) => self::resolveAuditableLabel($record) ?? ($record->auditable_id ? "#{$record->auditable_id}" : null))
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

    public static function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with(['user', 'auditable']);
    }

    protected static function resolveAuditableLabel(\OwenIt\Auditing\Models\Audit $record): ?string
    {
        $auditable = $record->auditable;

        if ($auditable instanceof Model) {
            $label = self::resolveModelDisplayName($auditable);

            if (filled($label)) {
                return $label;
            }
        }

        if ($record->auditable_id) {
            return "#{$record->auditable_id}";
        }

        return null;
    }

    protected static function resolveActorLabel(\OwenIt\Auditing\Models\Audit $record): string
    {
        if ($record->user instanceof Model) {
            $label = self::resolveModelDisplayName($record->user);

            if (filled($label)) {
                return $label;
            }
        }

        if (self::isLoginAudit($record) && $record->auditable instanceof Model) {
            $label = self::resolveModelDisplayName($record->auditable);

            if (filled($label)) {
                return $label;
            }
        }

        if ($record->user_id) {
            $name = User::where('employee_id', $record->user_id)->first();
            if(!empty($name)) {
                return $name->name;
            }
            return 'Unknown';
        }

        return 'System';
    }

    protected static function resolveModelDisplayName(Model $model): ?string
    {
        if (method_exists($model, 'getDisplayName')) {
            $displayName = $model->getDisplayName();

            if (filled($displayName)) {
                return (string) $displayName;
            }
        }

        $preferredAttributes = [
            'name',
            'full_name',
            'display_name',
            'title',
            'label',
            'username',
            'email',
            'code',
            'reference',
        ];

        foreach ($preferredAttributes as $attribute) {
            $value = data_get($model, $attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        if (method_exists($model, '__toString')) {
            $stringValue = (string) $model;

            if (filled($stringValue) && $stringValue !== get_class($model)) {
                return $stringValue;
            }
        }

        return null;
    }

    protected static function resolveEventLabel(\OwenIt\Auditing\Models\Audit $record): string
    {
        if (self::isLoginAudit($record)) {
            return 'Login';
        }

        return $record->event ? Str::headline($record->event) : 'Unknown';
    }

    protected static function resolveEventColor(\OwenIt\Auditing\Models\Audit $record): string
    {
        if (self::isLoginAudit($record)) {
            return 'info';
        }

        return match ($record->event) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            'restored' => 'info',
            default => 'gray',
        };
    }

    protected static function isLoginAudit(\OwenIt\Auditing\Models\Audit $record): bool
    {
        $auditableType = ltrim((string) $record->auditable_type, '\\');

        if ($record->event !== 'updated' || $auditableType !== User::class) {
            return false;
        }

        return self::auditHasLoginFieldChange($record);
    }

    protected static function auditHasLoginFieldChange(\OwenIt\Auditing\Models\Audit $record): bool
    {
        $newValues = self::normalizeAuditValues($record->new_values);
        $oldValues = self::normalizeAuditValues($record->old_values);

        return array_key_exists('last_login_at', $newValues) || array_key_exists('last_login_at', $oldValues);
    }

    protected static function normalizeAuditValues(mixed $values): array
    {
        if (is_array($values)) {
            return $values;
        }

        if (is_string($values) && filled($values)) {
            $decoded = json_decode($values, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
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

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'manager', 'superadmin']);
    }
}
