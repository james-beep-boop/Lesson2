<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AbsoluteSessionTimeout;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->topNavigation()
            ->brandName('ARES — Admin')
            ->colors([
                'primary' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                function (): HtmlString {
                    $logoutUrl = route('tab-guard-logout');

                    if (auth()->check()) {
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

                    return new HtmlString(<<<'HTML'
<script>
(function(){sessionStorage.setItem('ares_tab_active','1');})();
</script>
HTML);
                }
            )
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
                AbsoluteSessionTimeout::class,
            ]);
    }
}
