{{-- ARES override: wraps fi-logo + attribution in a branded box. Re-apply after Filament upgrades. --}}
@props([
    'heading' => null,
    'logo' => true,
    'subheading' => null,
])

<header class="fi-simple-header">
    @if ($logo)
        <div class="rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5" style="padding: 1rem 1.5rem; text-align: center; margin-bottom: 0.75rem;">
            <x-filament-panels::logo />
        </div>
    @endif

    @if (filled($heading))
        <h1 class="fi-simple-header-heading">
            {{ $heading }}
        </h1>
    @endif

    @if (filled($subheading))
        <p class="fi-simple-header-subheading">
            {{ $subheading }}
        </p>
    @endif
</header>
