@if($translationPanelOpen)
    @php
        $translatedHtml = $translatedContent
            ? \Illuminate\Support\Str::markdown($translatedContent, ['html_input' => 'strip'])
            : '';
    @endphp

    {{--
        Outer div initialises an Alpine component that:
        - Automatically calls translatePreview() once the panel is mounted
        - Provides a printTranslation() helper used by the Print button
        x-init fires after the DOM is ready, ensuring wire:stream="translatedContent" exists.
    --}}
    <div
        class="mt-4"
        x-data="{
            printTranslation() {
                const el = document.getElementById('translation-printable');
                if (!el) return;
                const w = window.open('', '_blank', 'width=820,height=640');
                w.document.write(
                    '<!DOCTYPE html><html><head><title>Swahili Translation<\/title>'
                    + '<style>body{font-family:Georgia,serif;max-width:760px;margin:40px auto;padding:0 20px;line-height:1.7}'
                    + 'h1,h2,h3{margin-top:1.5em}table{border-collapse:collapse;width:100%}'
                    + 'td,th{border:1px solid #ccc;padding:.4em .6em}<\/style>'
                    + '<\/head><body>' + el.innerHTML + '<\/body><\/html>'
                );
                w.document.close();
                w.focus();
                w.print();
            }
        }"
        x-init="$nextTick(() => $wire.translatePreview())"
    >
        <x-filament::section heading="Swahili Translation — Preview Only">
            <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                Preview only — this translation has not been saved to the database. May take up to one minute.
            </p>

            @if($translatedHtml)
                {{-- Final rendered output after streaming completes --}}
                <div
                    id="translation-printable"
                    class="prose max-w-none rounded-lg border border-gray-200 bg-white p-4 dark:prose-invert dark:border-gray-700 dark:bg-gray-900"
                >
                    {!! $translatedHtml !!}
                </div>
            @else
                {{-- Streaming in progress --}}
                <div class="min-h-16 whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <span wire:stream="translatedContent"></span>
                    <span class="italic text-gray-400 dark:text-gray-500">Translating to Swahili…</span>
                </div>
            @endif

            <div class="mt-3 flex gap-2">
                @if($translatedHtml)
                    <x-filament::button
                        x-on:click="printTranslation()"
                        color="gray"
                        size="sm"
                        icon="heroicon-o-printer"
                    >
                        Print
                    </x-filament::button>
                @endif

                <x-filament::button wire:click="$set('translationPanelOpen', false)" color="gray" size="sm">
                    Close
                </x-filament::button>
            </div>
        </x-filament::section>
    </div>
@endif
