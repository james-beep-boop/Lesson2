{{-- ARES override: adds "By ARES Education" attribution below brand name. Re-apply after Filament upgrades. --}}
@php
    $brandName = filament()->getBrandName();
    $brandLogo = filament()->getBrandLogo();
    $darkModeBrandLogo = filament()->getDarkModeBrandLogo();
    $hasDarkModeBrandLogo = filled($darkModeBrandLogo);
@endphp

@if ($brandLogo instanceof \Illuminate\Contracts\Support\Htmlable)
    <div class="fi-logo" style="line-height: 1.2;">
        {{ $brandLogo }}
        <span style="display: block; font-size: 0.875rem; font-weight: 400; letter-spacing: 0; opacity: 0.7;">
            By <a href="https://areseducation.org" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;">ARES Education</a>
        </span>
    </div>
    @if ($hasDarkModeBrandLogo)
        <div class="fi-logo fi-logo-dark" style="line-height: 1.2;">
            {{ $darkModeBrandLogo }}
            <span style="display: block; font-size: 0.875rem; font-weight: 400; letter-spacing: 0; opacity: 0.7;">
                By <a href="https://areseducation.org" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;">ARES Education</a>
            </span>
        </div>
    @endif
@elseif (filled($brandLogo))
    <div style="line-height: 1.2;">
        <img alt="{{ $brandName }}" src="{{ $brandLogo }}" class="fi-logo" />
        <span style="display: block; font-size: 0.875rem; font-weight: 400; opacity: 0.7;">
            By <a href="https://areseducation.org" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;">ARES Education</a>
        </span>
    </div>
@else
    <div class="fi-logo" style="display: block; line-height: 1.1;">
        <span style="display: block;">{{ $brandName }}</span>
        <span style="display: block; font-size: 0.875rem; font-weight: 400; letter-spacing: 0; opacity: 0.7; margin-top: 0.1rem;">By <span @click.stop="window.open('https://areseducation.org','_blank')" style="text-decoration: underline; cursor: pointer;">ARES Education</span></span>
    </div>
@endif
