<div
    x-data="{
        preview: '',
        renderMarkdown(val) {
            this.preview = window.marked ? marked.parse(val || '') : (val || '');
        },
        init() {
            // Initial render from current Livewire state
            const initial = $wire.get('data.content') ?? '';
            if (window.marked) {
                this.renderMarkdown(initial);
            } else {
                // marked.js may still be loading; retry after script loads
                const script = document.querySelector('script[src*=\"marked\"]');
                if (script) {
                    script.addEventListener('load', () => this.renderMarkdown(initial), { once: true });
                }
            }

            // Watch for Livewire-driven updates (e.g. file upload populates content)
            $wire.watch('data.content', (val) => this.renderMarkdown(val ?? ''));
        }
    }"
    x-on:markdown-input.window="renderMarkdown($event.detail.value)"
>
    {{-- Label matches Filament's field label style --}}
    <label class="fi-fo-field-wrp-label fi-label block text-sm font-medium text-gray-950 dark:text-white mb-1">
        Preview
    </label>

    <div
        x-html="preview || '<p style=\'color:#9ca3af\'>Start typing to see a preview…</p>'"
        class="prose prose-sm max-w-none overflow-y-auto rounded-lg border border-gray-300 bg-white px-4 py-3 dark:border-white/20 dark:bg-gray-900 dark:prose-invert"
        style="min-height: 30rem;"
    ></div>

    <script>
        (function () {
            if (!window.marked && !document.querySelector('script[src*="marked"]')) {
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/marked/marked.min.js';
                document.head.appendChild(s);
            }
        })();
    </script>
</div>
