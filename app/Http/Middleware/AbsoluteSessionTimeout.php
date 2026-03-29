<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

class AbsoluteSessionTimeout
{
    /**
     * Expire the session after an absolute wall-clock duration, regardless of activity.
     *
     * This is necessary because Safari's "Restore Previous Session" feature keeps session
     * cookies alive across browser restarts, bypassing the expire_on_close cookie setting.
     * By storing the session start time server-side, we can enforce a hard expiry.
     *
     * Duration is configured via SESSION_ABSOLUTE_LIFETIME (minutes). Default: 480 (8 hours).
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (auth()->check()) {
            $startedAt = $request->session()->get('_absolute_started_at');

            if (! $startedAt) {
                $request->session()->put('_absolute_started_at', now()->timestamp);
            } elseif (now()->timestamp - $startedAt > (int) env('SESSION_ABSOLUTE_LIFETIME', 480) * 60) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->to(Filament::getPanel('app')->getLoginUrl());
            }
        }

        return $next($request);
    }
}
