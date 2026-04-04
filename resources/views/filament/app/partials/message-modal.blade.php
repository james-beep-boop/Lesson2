@if($showMessageModal && $selectedVersion)
<div
    class="mt-6"
    x-data="{}"
>
<x-filament::section heading="Message About This Lesson">

    {{-- Recipient type selector --}}
    <div class="mb-4">
        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Send to:</p>
        <div class="flex flex-wrap gap-2">
            <x-filament::button
                wire:click="openMessageModal('author')"
                :color="$messageRecipientType === 'author' ? 'primary' : 'gray'"
                size="sm"
            >
                Message Author
            </x-filament::button>

            @php $subjectAdmin = $record->subjectGrade->subjectAdmin; @endphp
            @if($subjectAdmin)
                <x-filament::button
                    wire:click="openMessageModal('subject_admin')"
                    :color="$messageRecipientType === 'subject_admin' ? 'primary' : 'gray'"
                    size="sm"
                >
                    Subject Administrator
                </x-filament::button>
            @else
                <x-filament::button color="gray" size="sm" disabled>
                    Subject Administrator (none assigned)
                </x-filament::button>
            @endif

            <x-filament::button
                wire:click="openMessageModal('site_admin')"
                :color="$messageRecipientType === 'site_admin' ? 'primary' : 'gray'"
                size="sm"
            >
                Site Administrator
            </x-filament::button>

            <x-filament::button
                wire:click="openMessageModal('any_user')"
                :color="$messageRecipientType === 'any_user' ? 'primary' : 'gray'"
                size="sm"
            >
                Any User
            </x-filament::button>
        </div>
    </div>

    {{-- Recipient information --}}
    <div class="mb-4 rounded bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:bg-blue-950 dark:text-blue-200">
        @if($messageRecipientType === 'author')
            <strong>To:</strong> {{ $selectedVersion->contributor->name ?? '?' }}
        @elseif($messageRecipientType === 'subject_admin' && $subjectAdmin)
            <strong>To:</strong> {{ $subjectAdmin->name }}
        @elseif($messageRecipientType === 'site_admin')
            <strong>To:</strong> All Site Administrators — this message will be sent to every site admin.
        @elseif($messageRecipientType === 'any_user')
            <strong>To:</strong>
            @if($messageToUserId)
                {{ \App\Models\User::find($messageToUserId)?->name ?? 'Unknown user' }}
                <button wire:click="$set('messageToUserId', null)" class="ml-2 text-blue-600 underline">Change</button>
            @else
                <em>Search and select a user below</em>
            @endif
        @endif
    </div>

    {{-- Any-user search --}}
    @if($messageRecipientType === 'any_user' && !$messageToUserId)
        <div class="mb-4">
            <input
                wire:model.live="userSearchQuery"
                type="text"
                placeholder="Search by name or email…"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
            >
            @if(strlen($userSearchQuery) >= 1)
                <ul class="mt-1 max-h-48 overflow-auto rounded-lg border border-gray-200 bg-white shadow dark:border-gray-700 dark:bg-gray-800">
                    @forelse($this->getMessageUserSearchResults() as $u)
                        <li>
                            <button
                                wire:click="selectMessageUser({{ $u->id }})"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                {{ $u->name }} <span class="text-gray-400 text-xs">{{ $u->email }}</span>
                            </button>
                        </li>
                    @empty
                        <li class="px-3 py-2 text-sm text-gray-400">No users found.</li>
                    @endforelse
                </ul>
            @endif
        </div>
    @endif

    {{-- Subject --}}
    <div class="mb-3">
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Subject</label>
        <input
            wire:model="messageSubject"
            type="text"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        >
    </div>

    {{-- Body --}}
    <div class="mb-4">
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Message</label>
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
        <x-filament::button
            wire:click="$set('showMessageModal', false)"
            color="gray"
        >
            Cancel
        </x-filament::button>
    </div>

</x-filament::section>
</div>
@endif
