<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Model;


class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Employee Record';

    protected static ?string $pluralLabel = 'Employee Records';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'employee_id'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name; // What shows as the main title
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Employee ID' => $record->employee_id,
            'Position' => $record->position];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Personal Information')
                    ->description('Basic user information and identity details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('employee_id')
                                    ->label('Employee ID')
                                    ->required()
                                    ->unique(User::class, 'employee_id', ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('Enter Employee ID')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('name')
                                    ->label('Full Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Enter Full Name')
                                    ->columnSpan(1),
                                
                                Forms\Components\TextInput::make('email')
                                    ->label('Email Address')
                                    ->email()
                                    ->unique(User::class, 'email', ignoreRecord: true)
                                    ->maxLength(255)
                                    ->placeholder('user@example.com')
                                    ->prefixIcon('heroicon-m-envelope')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('contact_number')
                                    ->label('Contact Number')
                                    ->required()
                                    ->unique(User::class, 'contact_number', ignoreRecord: true)
                                    ->maxLength(20)
                                    ->placeholder('Enter Contact Number')
                                    ->columnSpan(1),
                            ]),
                    
                    ])
                    ->columns(1),

                Section::make('Security & Authentication')
                    ->description('Password and security settings')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->maxLength(255)
                            ->placeholder('Enter a secure password')
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->revealable()
                            ->prefixIcon('heroicon-m-lock-closed')
                            ->helperText('Password must be at least 8 characters long'),
                    ])
                    ->columns(1),

                Section::make('Permissions & Status')
                    ->description('User roles, permissions, and account status')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('role')
                                    ->label('User Role')
                                    ->options([
                                        'user' => 'User',
                                        'admin' => 'Administrator',
                                        'manager' => 'Manager',
                                    ])
                                    ->default('user')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->prefixIcon('heroicon-m-user-circle')
                                    ->columnSpan(1),
                                    
                                Forms\Components\Select::make('status')
                                    ->label('User Status')
                                    ->options([
                                        'active' => 'Active',
                                        'on_leave' => 'On Leave',
                                        'resigned' => 'Resigned',
                                    ])
                                    ->default('active')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->prefixIcon('heroicon-m-user-circle')
                                    ->columnSpan(1),
                                
                            ]),
                    ])
                    ->columns(1),

                Section::make('Miscellaneous')
                    ->description('Additional user information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('position')
                                    ->label('Position')
                                    ->required()
                                    ->maxLength(100)
                                    ->columnSpan(1),

                                Forms\Components\Select::make('shift')
                                     ->label('Shift')
                                     ->options([
                                         'morning' => 'Morning',
                                         'midday' => 'Midday',
                                         'night' => 'Night',
                                     ])
                                     ->default('morning')
                                     ->required()
                                     ->columnSpan(1),

                                DatePicker::make('date_hired')
                                    ->label('Date Hired')
                                    ->required()
                                    ->prefixIcon('heroicon-m-calendar')
                                    ->columnSpan(1),
                        ])
                    ]),

                Section::make('Custom Fields')
                    ->description('Additional custom user attributes')
                    ->schema([
                        Forms\Components\Repeater::make('custom_fields')
                            ->label('Custom Fields')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('key')
                                            ->label('Field Name')
                                            ->required()
                                            ->placeholder('e.g., department, phone, etc.')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\Select::make('type')
                                            ->label('Field Type')
                                            ->options([
                                                'text' => 'Text',
                                                'number' => 'Number',
                                                'email' => 'Email',
                                                'url' => 'URL',
                                                'date' => 'Date',
                                                'boolean' => 'Yes/No',
                                            ])
                                            ->default('text')
                                            ->required()
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('value')
                                            ->label('Value')
                                            ->placeholder('Enter field value')
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->cloneable()
                            ->deleteAction(
                                fn (Forms\Components\Actions\Action $action) => $action
                                    ->requiresConfirmation()
                            )
                            ->addActionLabel('Add Custom Field')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(true)
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_id')
                    ->label('Employee ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('position')
                    ->label('Position')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('date_hired')
                    ->label('Date Hired')
                    ->date('F d, Y')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact_number')
                    ->label('Contact Number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('shift')
                    ->label('Shift')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'morning' => 'Morning',
                        'midday' => 'Midday',
                        'night' => 'Night',
                        default => $state,
                    })
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'success',
                        'manager' => 'info',
                        'user' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'manager' => 'info',
                        'user' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Active',
                        'on_leave' => 'On Leave',
                        'resigned' => 'Resigned',
                        default => 'Inactive',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime('F d, Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'user' => 'User',
                        'admin' => 'Administrator',
                        'manager' => 'Manager',
                    ]),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'on_leave' => 'On Leave',
                        'resigned' => 'Resigned',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', '!=', 'admin');
    }
}
