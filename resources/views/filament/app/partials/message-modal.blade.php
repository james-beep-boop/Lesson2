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
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button
                    wire:click="openMessageModal('author')"
                    :color="$messageRecipientType === 'author' ? 'primary' : 'gray'"
                >
                    Message Author
                </x-filament::button>

                @if($subjectAdmin && $subjectAdmin->id !== ($user?->id))
                    <x-filament::button
                        wire:click="openMessageModal('subject_admin')"
                        :color="$messageRecipientType === 'subject_admin' ? 'primary' : 'gray'"
                    >
                        Message Subject Admin
                    </x-filament::button>
                @elseif(!$subjectAdmin)
                    <x-filament::button color="gray" disabled>
                        Message Subject Admin (none assigned)
                    </x-filament::button>
                @endif

                <x-filament::button
                    wire:click="openMessageModal('site_admin')"
                    :color="$messageRecipientType === 'site_admin' ? 'primary' : 'gray'"
                >
                    Message Site Admin
                </x-filament::button>
            </div>
        </div>

        {{-- Recipient information --}}
        <div class="text-sm text-gray-900 dark:text-gray-100">
            @if($messageRecipientType === 'author')
                <strong>To:</strong> {{ $selectedVersion->contributor->name ?? '?' }}
            @elseif($messageRecipientType === 'subject_admin' && $subjectAdmin)
                <strong>To:</strong> {{ $subjectAdmin->name }}
            @elseif($messageRecipientType === 'site_admin')
                <strong>To:</strong> All Site Administrators — this message will be sent to every site admin.
            @endif
        </div>

        {{-- Subject --}}
        <div>
            <p class="mb-1 text-sm font-bold text-gray-950 dark:text-white">Subject:</p>
            <x-filament::input.wrapper>
                <x-filament::input
                    wire:model="messageSubject"
                    type="text"
                />
            </x-filament::input.wrapper>
        </div>

        {{-- Body --}}
        <div>
            <p class="mb-1 text-sm font-bold text-gray-950 dark:text-white">Message</p>
            <x-filament::input.wrapper>
                <textarea
                    wire:model="messageBody"
                    rows="10"
                    class="block w-full border-none bg-transparent px-3 py-1.5 text-sm leading-6 text-gray-950 placeholder:text-gray-400 focus:ring-0 focus:outline-none dark:text-white"
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
