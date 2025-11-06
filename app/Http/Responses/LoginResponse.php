<?php

namespace App\Http\Responses;

use Filament\Pages\Dashboard;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;
use Filament\Http\Responses\Auth\LoginResponse as BaseLoginResponse;

class LoginResponse extends BaseLoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        // Check the user's role and redirect accordingly
        if (auth()->user()->role == 'manager') {
            return redirect()->to(route('filament.app.resources.stock-entries.index'));
        } elseif (auth()->user()->is_employee) {
            return redirect()->to(Dashboard::getUrl(panel: 'app'));
        }

        // Default redirect if no specific role condition is met
        return parent::toResponse($request);
    }
}
