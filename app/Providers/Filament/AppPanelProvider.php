<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard as AppDashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Joaopaulolndev\FilamentEditProfile\FilamentEditProfilePlugin;
use Filament\Navigation\MenuItem;
use App\Filament\Pages\Auth\Login;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        \Log::info('Configuring Filament panel', [
            'provider' => static::class,
            'dashboard_class' => \App\Filament\Pages\Dashboard::class,
        ]);

        return $panel
            ->homeUrl(fn () => route('filament.app.pages.dashboard'))
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                'dashboard' => AppDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Widgets Here
            ])
            ->plugins([
                    FilamentEditProfilePlugin::make()
                        ->slug('my-profile')
                        ->setTitle('My Profile')
                        ->setNavigationLabel('My Profile')
                        ->setNavigationGroup('Group Profile')
                        ->setIcon('heroicon-o-user')
                        ->setSort(10)
                        // ->canAccess(fn () => auth()->user()->id === 1)
                        ->shouldRegisterNavigation(false)
                        ->shouldShowEmailForm()
                        ->shouldShowDeleteAccountForm(false)
                        ->shouldShowSanctumTokens()
                        ->shouldShowBrowserSessionsForm()
                        ->shouldShowAvatarForm()
            ])
            ->login(Login::class)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('Edit Profile')
                    ->url(fn (): string => route('filament.app.pages.my-profile'))
                    ->icon('heroicon-m-user-circle'),
            ])
            ->colors([
                'primary' => [
                    50 => '#f0fdfa',
                    100 => '#ccfbf1',
                    200 => '#99f6e4',
                    300 => '#5eead4',
                    400 => '#2dd4bf',
                    500 => '#14b8a6',
                    600 => '#0d9488',
                    700 => '#0f766e',
                    800 => '#115e59',
                    900 => '#134e4a',
                    950 => '#042f2e',
                ],
                'secondary' => [
                    50 => '#ecfeff',
                    100 => '#cffafe',
                    200 => '#a5f3fc',
                    300 => '#67e8f9',
                    400 => '#22d3ee',
                    500 => '#06b6d4',
                    600 => '#0891b2',
                    700 => '#0e7490',
                    800 => '#155e75',
                    900 => '#164e63',
                    950 => '#083344',
                ],
            ])
            ->databaseNotifications();
    }
}
