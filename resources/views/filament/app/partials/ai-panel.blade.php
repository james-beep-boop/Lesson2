@if($aiPanelOpen && $canAskAi)
    <div id="ai-panel-anchor" x-on:scroll-to-ai-panel.window="requestAnimationFrame(() => requestAnimationFrame(() => $el.scrollIntoView({behavior:'smooth', block:'start'})))">
    <x-filament::section heading="Ask AI" class="mt-4">
        <p class="mb-2 text-sm text-gray-500">AI suggestions are read-only. Copy anything useful into your editor manually.</p>
        <div class="flex gap-2 mb-3">
            @foreach(['Suggest improvements', 'Check for clarity', 'Simplify language', 'Ask a question'] as $quick)
                <x-filament::button wire:click="$set('aiPrompt', '{{ $quick }}')" color="gray" size="sm">
                    {{ $quick }}
                </x-filament::button>
            @endforeach
        </div>
        <textarea wire:model="aiPrompt" rows="3" class="w-full rounded border border-gray-300 p-2 text-sm" placeholder="What would you like help with?"></textarea>
        <div class="mt-3 rounded bg-gray-50 border p-3 text-sm whitespace-pre-wrap min-h-[2rem]">
            <span wire:stream="aiResponse">{{ $aiResponse }}</span>
            <span wire:loading wire:target="submitAiPrompt" class="text-gray-400 italic">Thinking…</span>
        </div>
        <div class="mt-3 flex gap-2">
            <x-filament::button wire:click="submitAiPrompt" wire:loading.attr="disabled" wire:target="submitAiPrompt" size="sm">Submit</x-filament::button>
            <x-filament::button wire:click="closeAiPanel" color="gray" size="sm">Close</x-filament::button>
        </div>
    </x-filament::section>
    </div>
@endif
