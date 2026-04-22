@if($translationPanelOpen)
    {{--
        Translation starts once when the panel mounts, which keeps the request
        lifecycle aligned with the visible UI state.
    --}}
    <div
        class="mt-4"
        x-data="{
            started: false,
            init() {
                if (this.started) {
                    return;
                }

                this.started = true;
                this.$nextTick(() => $wire.translatePreview());
            }
        }"
        x-on:open-translation-print.window="window.open($event.detail.url, '_blank')"
    >
        <x-filament::section heading="Swahili Translation">
            <x-slot name="afterHeader">
                <div class="flex gap-2">
                    @if($translationComplete && filled($translatedContent))
                        <x-filament::button
                            wire:click="preparePrintTranslation"
                            color="gray"
                            size="sm"
                            icon="heroicon-o-printer"
                        >
                            Print
                        </x-filament::button>

                        <x-filament::button
                            wire:click="downloadTranslationPdf"
                            color="gray"
                            size="sm"
                            icon="heroicon-o-arrow-down-tray"
                        >
                            Download PDF
                        </x-filament::button>

                        <x-filament::button
                            wire:click="mountAction('emailTranslationPdf')"
                            color="gray"
                            size="sm"
                            icon="heroicon-o-envelope"
                        >
                            Email PDF
                        </x-filament::button>
                    @endif

                    <x-filament::button wire:click="closeTranslationPanel" color="gray" size="sm">
                        Close
                    </x-filament::button>
                </div>
            </x-slot>

            <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                Preview only — this translation has not been saved to the database. May take up to one minute.
            </p>

            @if($translationComplete && filled($translatedContent))
                <div
                    wire:key="translation-viewer"
                    x-data="toastViewer({{ Js::from($translatedContent) }})"
                    class="ares-toast-viewer rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                >
                    <div data-toast-viewer wire:ignore></div>
                </div>
            @else
                {{-- Streaming in progress --}}
                <div class="min-h-16 whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <span wire:stream="translatedContent"></span>
                    <span class="italic text-gray-400 dark:text-gray-500">Translating to Swahili…</span>
                </div>
            @endif
        </x-filament::section>
    </div>
@endif
