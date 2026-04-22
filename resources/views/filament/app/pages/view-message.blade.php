<x-filament-panels::page>
    @php
        $displayBody = preg_replace('/\n*\[deletion_request:\d+\]\s*$/', '', $this->record->body);
        $renderedBody = preg_replace_callback(
            '/\[(?<label>[^\]\n]+)\]\((?<markdown_url>https?:\/\/[^\s)<>]+)\)|(?<bare_url>https?:\/\/[^\s<>]+)/',
            function (array $matches): string {
                if (filled($matches['markdown_url'])) {
                    return '<a href="'.e($matches['markdown_url']).'" target="_blank" rel="noopener noreferrer" class="underline">'
                        .e($matches['label'])
                        .'</a>';
                }

                $url = rtrim($matches['bare_url'], '.,;:)!"\'');

                return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" class="underline">'.e($url).'</a>';
            },
            e($displayBody)
        );
    @endphp

    <div class="max-w-3xl space-y-4">

        {{-- Message header card --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <dl class="space-y-3 text-sm">
                <div class="flex gap-4">
                    <dt class="w-16 shrink-0 font-medium text-gray-500 dark:text-gray-400">From</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $this->record->fromUser->name }}</dd>
                </div>
                <div class="flex gap-4">
                    <dt class="w-16 shrink-0 font-medium text-gray-500 dark:text-gray-400">Subject</dt>
                    <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->subject }}</dd>
                </div>
                <div class="flex gap-4">
                    <dt class="w-16 shrink-0 font-medium text-gray-500 dark:text-gray-400">Received</dt>
                    <dd class="text-gray-500 dark:text-gray-400">
                        {{ $this->record->created_at->format('d M Y, H:i') }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Message body --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div
                class="prose prose-sm dark:prose-invert max-w-none text-gray-800 dark:text-gray-200"
                data-testid="message-body"
            >
                {!! nl2br($renderedBody) !!}
            </div>
        </div>

    </div>
</x-filament-panels::page>
