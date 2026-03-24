<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\App\Pages\Profile;
use App\Filament\App\Pages\Register;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('/')
            ->login()
            ->registration(Register::class)
            ->passwordReset()
            ->emailVerification()
            ->profile(Profile::class, isSimple: false)
            ->topNavigation()
            ->brandName('ARES Lesson Repository')
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString('
<style>
/* Brand name — ~35% bigger than default body text (1rem → 1.5rem) */
.fi-logo { font-size: 1.5rem !important; font-weight: 700 !important; letter-spacing: -0.01em; }

/* Push nav items (Dashboard, Lessons, Inbox) to the right, just left of the user avatar */
.fi-topbar { display: flex; align-items: center; gap: 0; }
.fi-topbar-nav-groups { margin-left: auto !important; }
.fi-topbar-end { margin-left: 1rem !important; flex-shrink: 0; }
</style>
                ')
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_BEFORE,
                fn (): HtmlString => auth()->check()
                    ? new HtmlString(
                        '<p class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">'
                        . e(auth()->user()->getRoleLabel())
                        . '</p>'
                    )
                    : new HtmlString('')
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn () => view('components.ares-footer')
            )
            ->viteTheme('resources/css/app.css')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
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
            ->authMiddleware([
                Authenticate::class,
                EnsureEmailIsVerified::class,
            ]);
    }
}
