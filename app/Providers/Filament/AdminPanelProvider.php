<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\ContractStats;
use App\Support\Settings;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: (bool) config('portal.require_2fa'),
            )
            ->brandName('CSZ Contract Portal')
            ->brandLogo(fn () => new HtmlString(
                '<span style="font-weight:700;letter-spacing:.02em"><span style="color:#64BCE9">CSZ</span> <span style="color:#7C8286">Contract Portal</span></span>'
            ))
            ->colors([
                'primary' => Color::hex('#2E9BD6'),
                'gray' => Color::Slate,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications(false)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->widgets([ContractStats::class])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('partials.portal-styles'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
