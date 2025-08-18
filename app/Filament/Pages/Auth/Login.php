<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class Login extends BaseLogin
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('employee_id')
                    ->label('Employee ID')
                    ->required()
                    ->autocomplete()
                    ->autofocus(),
                    
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required(),
                    
                Checkbox::make('remember')
                    ->label('Remember me'),
            ])
            ->statePath('data');
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        Log::info('Getting credentials for employee_id: ' . $data['employee_id']);
        
        // Find user by employee_id
        $user = User::where('employee_id', $data['employee_id'])->first();
        
        if (!$user) {
            Log::warning('User not found for employee_id: ' . $data['employee_id']);
            throw ValidationException::withMessages([
                'employee_id' => 'Invalid employee ID or password.',
            ]);
        }

        // Verify password manually
        if (!Hash::check($data['password'], $user->password)) {
            Log::warning('Password verification failed for employee_id: ' . $data['employee_id']);
            throw ValidationException::withMessages([
                'employee_id' => 'Invalid employee ID or password.',
            ]);
        }

        // Check if user can access panel
        if (!$user instanceof \Filament\Models\Contracts\FilamentUser) {
            Log::error('User does not implement FilamentUser interface');
            throw ValidationException::withMessages([
                'employee_id' => 'Access denied.',
            ]);
        }

        Log::info('Authentication successful for employee_id: ' . $data['employee_id']);
        
        // Return credentials using the user's email (which Laravel auth expects)
        return [
            'email' => $user->email,
            'password' => $data['password'],
        ];
    }
}