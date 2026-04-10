@if($showEmailDocxModal && $selectedVersion)
<div class="mt-6">
<x-filament::section heading="Email .docx">

    <p class="mb-4 text-sm text-gray-500">
        Send the current version (v{{ $selectedVersion->version }}) as a .docx attachment to any email address.
    </p>

    <div class="mb-3">
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Recipient Email <span class="text-red-500">*</span>
        </label>
        <input
            wire:model="emailDocxTo"
            type="email"
            placeholder="recipient@example.com"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 @error('emailDocxTo') border-red-400 @enderror"
        >
        @error('emailDocxTo')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="mb-4">
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Optional message
        </label>
        <textarea
            wire:model="emailDocxMessage"
            rows="4"
            placeholder="Add a note to include in the email body…"
            class="w-full rounded-lg border border-gray-300 p-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        ></textarea>
    </div>

    <div class="flex gap-2">
        <x-filament::button
            wire:click="sendEmailDocx"
            wire:loading.attr="disabled"
            wire:target="sendEmailDocx"
            icon="heroicon-o-paper-airplane"
        >
            Send .docx
        </x-filament::button>
        <x-filament::button
            wire:click="$set('showEmailDocxModal', false)"
            color="gray"
        >
            Cancel
        </x-filament::button>
    </div>

</x-filament::section>
</div>
@endif
