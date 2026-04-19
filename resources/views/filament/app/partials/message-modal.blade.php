@if($showMessageModal && $selectedVersion)
@php $subjectAdmin = $record->subjectGrade->subjectAdmin; @endphp
<div
    class="mt-6"
    x-data="{}"
>
<x-filament::section>
    <div class="mb-5 flex items-center gap-3">
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

    <div class="space-y-4">
        {{-- Recipient type selector --}}
        <div>
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
            <label class="mb-1 block text-sm font-bold text-gray-700 dark:text-gray-300">Subject:</label>
            <input
                wire:model="messageSubject"
                type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
            >
        </div>

        {{-- Body --}}
        <div>
            <label class="mb-1 block text-sm font-bold text-gray-700 dark:text-gray-300">Message</label>
            <textarea
                wire:model="messageBody"
                rows="10"
                class="w-full rounded-lg border border-gray-300 p-3 font-mono text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
            ></textarea>
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
