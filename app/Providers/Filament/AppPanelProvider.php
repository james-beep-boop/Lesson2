<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\Profile;
use App\Filament\App\Pages\Register;
use App\Filament\App\Pages\RequestPasswordReset;
use App\Filament\App\Resources\MessageResource;
use App\Models\Message;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Auth\Login;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
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
            ->passwordReset(RequestPasswordReset::class)
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

/* Push nav items (Dashboard, Lessons) to the right, just left of the user avatar */
.fi-topbar { display: flex; align-items: center; gap: 0; }
.fi-topbar-nav-groups { margin-left: auto !important; }
.fi-topbar-end { margin-left: 1rem !important; flex-shrink: 0; }

/* Hide the empty profile header placeholder in the user menu */
.fi-user-menu .fi-dropdown-header { display: none !important; }
</style>
                ')
            )
            ->userMenuItems([
                // url(null) + no label + no icon → becomes a $hasProfileHeader = true,
                // which places USER_MENU_PROFILE_BEFORE inside the dropdown (hidden via CSS above)
                'profile' => fn (Action $action) => $action->url(null)->label('')->icon(null),
                Action::make('messages')
                    ->label(function (): string {
                        $counts = Message::where('to_user_id', auth()->id())
                            ->selectRaw('COUNT(*) as total, SUM(read_at IS NULL) as unread')
                            ->first();

                        return 'Inbox: '.($counts->unread ?? 0).' / '.($counts->total ?? 0);
                    })
                    ->icon('heroicon-o-inbox')
                    ->url(fn (): string => MessageResource::getUrl('index'))
                    ->sort(-2),
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_BEFORE,
                fn (): HtmlString => auth()->check()
                    ? new HtmlString(
                        '<div class="fi-dropdown-list">'
                        .'<div style="padding:0.625rem 0.875rem 0.5rem;">'
                        .'<table style="border-collapse:collapse;font-size:0.8125rem;line-height:1.6;">'
                        .'<tr><td style="opacity:0.55;font-size:0.75rem;padding-right:0.625rem;white-space:nowrap;vertical-align:top;">Username:</td><td>'.e(auth()->user()->name).'</td></tr>'
                        .'<tr><td style="opacity:0.55;font-size:0.75rem;padding-right:0.625rem;white-space:nowrap;vertical-align:top;">Role:</td><td>'.e(auth()->user()->role_label).'</td></tr>'
                        .'<tr><td style="opacity:0.55;font-size:0.75rem;padding-right:0.625rem;white-space:nowrap;vertical-align:top;">Email:</td><td>'.e(auth()->user()->email).'</td></tr>'
                        .'</table>'
                        .'</div>'
                        .'</div>'
                    )
                    : new HtmlString('')
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_END,
                fn () => view('components.ares-footer'),
                scopes: Login::class,
            )
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
