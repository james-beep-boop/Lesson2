{{-- Inject marked.js before Alpine initialises this component. --}}
<script>
    (function () {
        if (!window.marked && !document.querySelector('script[src*="marked@17"]')) {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/marked@17.0.5/marked.min.js';
            s.integrity = 'sha384-tkjnnf9Tzhv5ZFrDroGvUExw9C3EVFo0RFRkzKR8ZX4b5Psoec4yb1PlD8Jh4j4H';
            s.crossOrigin = 'anonymous';
            document.head.appendChild(s);
        }
    })();
</script>

{{-- $wireProp: the Livewire property path to watch (default: 'data.content' for Create page) --}}
{{-- $initialContent: optional server-rendered initial value to avoid $wire.get() timing issues --}}
@php $prop = $wireProp ?? 'data.content'; @endphp

<div
    x-data="{
        preview: {{ Js::from($initialHtml ?? '') }},
        renderMarkdown(val) {
            this.preview = window.marked ? marked.parse(val || '') : (val || '');
        },
        init() {
            const initial = {{ Js::from($initialContent ?? null) }} ?? $wire.get('{{ $prop }}') ?? '';
            if (!this.preview) {
                const render = () => this.renderMarkdown(initial);
                if (window.marked) {
                    render();
                } else {
                    const wait = () => window.marked ? render() : setTimeout(wait, 30);
                    wait();
                }
            }
            $wire.watch('{{ $prop }}', (val) => this.renderMarkdown(val ?? ''));
        }
    }"
    x-on:markdown-input.window="renderMarkdown($event.detail.value)"
>
    <label class="fi-fo-field-wrp-label fi-label block text-sm font-medium text-gray-950 dark:text-white mb-1">
        Preview
    </label>

    <div
        x-html="preview || '<p style=\'color:#9ca3af\'>Start typing to see a preview\u2026</p>'"
        class="prose prose-sm max-w-none overflow-y-auto rounded-lg border border-gray-300 bg-white px-4 py-3 dark:border-white/20 dark:bg-gray-900 dark:prose-invert"
        style="min-height: 30rem;"
    ></div>
</div>
