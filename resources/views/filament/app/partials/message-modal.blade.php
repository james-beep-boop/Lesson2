@if($showMessageModal && $selectedVersion)
@php $subjectAdmin = $record->subjectGrade->subjectAdmin; @endphp
<div
    class="mt-6 w-full"
    x-data="{}"
>
<x-filament::section class="w-full max-w-none">
    <div class="mb-6 flex items-center gap-3">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
            Message About This Lesson
        </h2>
        <x-filament::button
            wire:click="$set('showMessageModal', false)"
            size="sm"
        >
            Return
        </x-filament::button>
    </div>

    <div class="space-y-6">
        {{-- Recipient type selector --}}
        <div class="w-full">
            <div class="flex flex-wrap gap-3">
                <x-filament::button
                    wire:click="openMessageModal('author')"
                    :color="$messageRecipientType === 'author' ? 'primary' : 'gray'"
                    size="sm"
                >
                    Message Author
                </x-filament::button>

                @if($subjectAdmin && $subjectAdmin->id !== ($user?->id))
                    <x-filament::button
                        wire:click="openMessageModal('subject_admin')"
                        :color="$messageRecipientType === 'subject_admin' ? 'primary' : 'gray'"
                        size="sm"
                    >
                        Message Subject Admin
                    </x-filament::button>
                @elseif(!$subjectAdmin)
                    <x-filament::button color="gray" size="sm" disabled>
                        Message Subject Admin (none assigned)
                    </x-filament::button>
                @endif

                <x-filament::button
                    wire:click="openMessageModal('site_admin')"
                    :color="$messageRecipientType === 'site_admin' ? 'primary' : 'gray'"
                    size="sm"
                >
                    Message Site Admin
                </x-filament::button>
            </div>
        </div>

        {{-- Recipient information --}}
        <div class="w-full text-sm text-gray-900 dark:text-gray-100">
            @if($messageRecipientType === 'author')
                <strong>To:</strong> {{ $selectedVersion->contributor->name ?? '?' }}
            @elseif($messageRecipientType === 'subject_admin' && $subjectAdmin)
                <strong>To:</strong> {{ $subjectAdmin->name }}
            @elseif($messageRecipientType === 'site_admin')
                <strong>To:</strong> All Site Administrators — this message will be sent to every site admin.
            @endif
        </div>

        {{-- Subject --}}
        <div class="w-full">
            <x-filament::input.wrapper
                class="w-full"
                label="Subject:"
            >
                <x-filament::input
                    wire:model="messageSubject"
                    type="text"
                    class="w-full max-w-none"
                />
            </x-filament::input.wrapper>
        </div>

        {{-- Body --}}
        <div class="w-full">
            <x-filament::input.wrapper
                class="w-full"
                label="Message"
            >
                <textarea
                    wire:model="messageBody"
                    rows="10"
                    class="block min-w-0 flex-1 resize-y border-0 bg-transparent px-3 py-2 font-mono text-sm text-gray-950 shadow-none outline-none focus:outline-none focus:ring-0 dark:bg-transparent dark:text-gray-100"
                ></textarea>
            </x-filament::input.wrapper>
        </div>

        <div class="flex gap-2">
            <x-filament::button
                wire:click="sendLessonMessage"
                wire:loading.attr="disabled"
                wire:target="sendLessonMessage"
            >
                Send Message
            </x-filament::button>
        </div>
    </div>

</x-filament::section>
</div>
@endif
