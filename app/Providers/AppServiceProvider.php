<?php

namespace App\Providers;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use App\Http\Responses\LoginResponse;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public $singletons = [
        \Filament\Http\Responses\Auth\Contracts\LoginResponse::class => LoginResponse::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Filament::serving(function () {
            Filament::registerNavigationGroups([
                NavigationGroup::make('Inventory Management'),
                NavigationGroup::make('Sales Management'),
                NavigationGroup::make('Product Management'),
                NavigationGroup::make('User Management'),
                NavigationGroup::make('Misc'),
            ]);
        });
    }
}
