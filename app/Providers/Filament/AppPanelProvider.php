<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\Profile;
use App\Filament\App\Pages\Register;
use App\Filament\App\Pages\RequestPasswordReset;
use App\Filament\App\Resources\MessageResource;
use App\Http\Middleware\AbsoluteSessionTimeout;
use App\Models\Message;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
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
            ->brandName('Kenya Lesson Plans')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => app(Vite::class)(['resources/css/app.css', 'resources/css/toast-ui.css', 'resources/js/app.js'])
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString('
<style>
/* Brand name — matches fi-header-heading (text-3xl = 1.875rem) */
.fi-logo { font-size: 1.875rem !important; font-weight: 700 !important; letter-spacing: -0.01em; }

/* Push nav items (Dashboard, Lessons) to the right, just left of the user avatar */
.fi-topbar { display: flex; align-items: center; gap: 0; }
.fi-topbar-nav-groups { margin-left: auto !important; }
.fi-topbar-end { margin-left: 1rem !important; flex-shrink: 0; }

/* Hide the empty profile header placeholder in the user menu */
.fi-user-menu .fi-dropdown-header { display: none !important; }

/* User menu panel is teleported to <body>, so the parent selector .fi-user-menu does not reach it.
   min-width on .fi-dropdown-panel is safe — panels already expand to fit content. */
.fi-dropdown-panel {
    min-width: 18rem;
}

/* Tailwind-compatible utility classes for custom blade views */
.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.mb-3 { margin-bottom: 0.75rem; }
</style>
                ')
            )
            ->userMenuItems([
                // url(null) + no label + no icon → becomes a $hasProfileHeader = true,
                // which places USER_MENU_PROFILE_BEFORE inside the dropdown (hidden via CSS above)
                'profile' => fn (Action $action) => $action->url(null)->label('')->icon(null),
                Action::make('messages')
                    ->label(function (): string {
                        $userId = auth()->id();
                        $counts = Cache::remember("user.{$userId}.inbox_counts", 30, function () use ($userId): array {
                            $row = Message::where('to_user_id', $userId)
                                ->selectRaw('COUNT(*) as total, SUM(read_at IS NULL) as unread')
                                ->first();

                            return ['total' => (int) ($row->total ?? 0), 'unread' => (int) ($row->unread ?? 0)];
                        });

                        return 'Inbox: '.($counts['unread'] ?? 0).' / '.($counts['total'] ?? 0);
                    })
                    ->icon('heroicon-o-inbox')
                    ->url(fn (): string => MessageResource::getUrl('index'))
                    ->sort(-2),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                function (): HtmlString {
                    $logoutUrl = route('tab-guard-logout');

                    if (auth()->check()) {
                        // Authenticated page: verify the tab guard marker is present.
                        // If it is missing the tab/browser was closed and reopened —
                        // force logout before any panel UI is shown.
                        // Use a form POST (not a redirect) to avoid CSRF-based forced logout.
                        return new HtmlString(<<<HTML
<script>
(function(){
    var K='ares_tab_active';
    if(!sessionStorage.getItem(K)){
        document.documentElement.style.visibility='hidden';
        var f=document.createElement('form');
        f.method='POST';
        f.action='{$logoutUrl}';
        var t=document.createElement('input');
        t.type='hidden';t.name='_token';
        t.value=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';
        f.appendChild(t);
        document.body.appendChild(f);
        f.submit();
    } else {
        sessionStorage.setItem(K,'1');
    }
})();
</script>
HTML);
                    }

                    // Unauthenticated page (login, register, etc.): stamp the marker
                    // so that the first authenticated page load passes the guard.
                    return new HtmlString(<<<'HTML'
<script>
(function(){sessionStorage.setItem('ares_tab_active','1');})();
</script>
HTML);
                }
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                function (): HtmlString {
                    if (! auth()->check()) {
                        return new HtmlString('');
                    }
                    $userId = auth()->id();
                    $hasUnread = Cache::remember(
                        "user.{$userId}.has_unread",
                        30,
                        fn () => Message::where('to_user_id', $userId)->whereNull('read_at')->exists()
                    );
                    if (! $hasUnread) {
                        return new HtmlString('');
                    }

                    return new HtmlString('
<style>
.fi-user-menu-trigger { position: relative; }
.fi-user-menu-trigger::after {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    width: 10px;
    height: 10px;
    border-radius: 9999px;
    background-color: #ef4444;
    box-shadow: 0 0 0 2px white;
    pointer-events: none;
}
.dark .fi-user-menu-trigger::after { box-shadow: 0 0 0 2px #1e293b; }
</style>
                    ');
                }
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_BEFORE,
                fn (): HtmlString => auth()->check()
                    ? new HtmlString((string) view('filament.app.partials.user-menu-profile', [
                        'user' => auth()->user()->loadMissing([
                            'subjectGradesAsAdmin.subject',
                            'subjectGrades.subject',
                        ]),
                    ]))
                    : new HtmlString('')
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
                AbsoluteSessionTimeout::class,
            ]);
    }
}
