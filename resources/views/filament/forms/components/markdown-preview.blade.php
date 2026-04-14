{{--
    Live read-only Markdown preview powered by Toast UI Viewer.
    Uses the same toastLiveViewer Alpine factory as the compare view, so
    tables, headings, and all GFM constructs render identically.

    Optional blade variables (passed via View::make(...)->viewData([...])):
      $wireProp      — Livewire property path to watch (default: 'data.content')
      $initialContent — server-rendered initial markdown (default: empty)
--}}
@php
    $prop    = $wireProp ?? 'data.content';
    $initial = $initialContent ?? '';
@endphp

<div
    x-data="toastLiveViewer({{ Js::from($initial) }})"
    x-init="_unwatchContent = $wire.watch('{{ $prop }}', v => update(v ?? ''))"
    x-on:markdown-input.window="update($event.detail.value)"
>
    <label class="fi-fo-field-wrp-label fi-label block text-sm font-medium text-gray-950 dark:text-white mb-1">
        Preview
    </label>

    {{-- wire:ignore: Livewire morphing would destroy and re-mount the viewer instance on every re-render --}}
    <div
        class="ares-toast-viewer overflow-y-auto rounded-lg border border-gray-300 dark:border-white/20"
        style="min-height: 30rem;"
        wire:ignore
    >
        <div data-toast-viewer></div>
    </div>
</div>
