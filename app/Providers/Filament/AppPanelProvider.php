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
</style>
                ')
            )
            ->userMenuItems([
                'profile' => fn (Action $action) => $action->hidden(),
                Action::make('messages')
                    ->label(function (): string {
                        $user = auth()->user();
                        $total = Message::where('to_user_id', $user->id)->count();
                        $unread = Message::where('to_user_id', $user->id)->whereNull('read_at')->count();

                        return "Messages: {$unread} / {$total}";
                    })
                    ->icon('heroicon-o-inbox')
                    ->url(fn (): string => MessageResource::getUrl('index')),
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_BEFORE,
                fn (): HtmlString => auth()->check()
                    ? new HtmlString(
                        '<div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 space-y-0.5">'
                        .'<p class="text-sm text-gray-800 dark:text-gray-200"><span class="text-xs text-gray-400 dark:text-gray-500">Username:</span> '.e(auth()->user()->name).'</p>'
                        .'<p class="text-sm text-gray-800 dark:text-gray-200"><span class="text-xs text-gray-400 dark:text-gray-500">Role:</span> '.e(auth()->user()->role_label).'</p>'
                        .'<p class="text-sm text-gray-800 dark:text-gray-200"><span class="text-xs text-gray-400 dark:text-gray-500">Email:</span> '.e(auth()->user()->email).'</p>'
                        .'</div>'
                    )
                    : new HtmlString('')
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn () => view('components.ares-footer')
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
