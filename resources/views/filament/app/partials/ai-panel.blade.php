@if($aiPanelOpen && $canAskAi)
    <div id="ai-panel-anchor" x-on:scroll-to-ai-panel.window="requestAnimationFrame(() => requestAnimationFrame(() => $el.scrollIntoView({behavior:'smooth', block:'start'})))">
    <x-filament::section heading="Ask AI" class="mt-4">
        <x-slot name="afterHeader">
            <x-filament::button wire:click="closeAiPanel" color="gray" size="sm">
                Close
            </x-filament::button>
        </x-slot>
        <p class="mb-2 text-sm text-gray-500">AI suggestions are read-only. Copy anything useful into your editor manually.</p>
        <div class="mb-3 flex flex-wrap gap-2">
            @foreach(['Suggest improvements', 'Check for clarity', 'Simplify language', 'Ask a question'] as $quick)
                <x-filament::button wire:click="useAiPrompt('{{ $quick }}')" wire:loading.attr="disabled" wire:target="useAiPrompt,submitAiPrompt" color="gray" size="sm">
                    {{ $quick }}
                </x-filament::button>
            @endforeach
        </div>
        <textarea wire:model="aiPrompt" wire:keydown.ctrl.enter.prevent="submitAiPrompt" wire:keydown.meta.enter.prevent="submitAiPrompt" rows="3" class="w-full rounded border border-gray-300 p-2 text-sm" placeholder="What would you like help with?"></textarea>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Press Ctrl+Enter or Cmd+Enter to submit.</p>
        @if(! $aiResponseComplete)
            <div class="mt-3 rounded bg-gray-50 border p-3 text-sm whitespace-pre-wrap min-h-[2rem]">
                <span wire:stream="aiResponse">{{ $aiResponse }}</span>
                <span wire:loading wire:target="submitAiPrompt" class="text-gray-400 italic">Thinking…</span>
            </div>
        @elseif(filled($aiResponse))
            <div
                class="mt-3"
                wire:key="ai-response-viewer"
                x-data="toastViewer({{ Js::from($aiResponse) }})"
            >
                <div data-toast-viewer wire:ignore></div>
            </div>
        @endif
    </x-filament::section>
    </div>
@endif
