<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <x-filament::section heading="New Lesson Plan">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                {{-- Subject Grade --}}
                <div>
                    <x-filament::input.wrapper label="Subject Grade">
                        <x-filament::input.select wire:model="subject_grade_id" required>
                            <option value="">Select...</option>
                            @foreach(\App\Models\SubjectGrade::with('subject')->get() as $sg)
                                @php $allowed = auth()->user()->isSiteAdmin() || auth()->user()->isSubjectAdminFor($sg); @endphp
                                @if($allowed)
                                    <option value="{{ $sg->id }}">{{ $sg->subject->name }} — Grade {{ $sg->grade }}</option>
                                @endif
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <x-filament::input.wrapper label="Day">
                        <x-filament::input wire:model="day" type="text" required placeholder="e.g. 1" />
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <x-filament::input.wrapper label="Language">
                        <x-filament::input.select wire:model="language">
                            <option value="en">English</option>
                            <option value="sw">Swahili</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Content">
            <textarea
                wire:model="content"
                rows="20"
                class="w-full rounded-lg border border-gray-300 p-3 font-mono text-sm focus:border-primary-500 focus:ring-primary-500"
                placeholder="Write your lesson plan in Markdown..."
                required
            ></textarea>
        </x-filament::section>

        <x-filament::section>
            <x-filament::input.wrapper label="Revision Note (optional)">
                <x-filament::input wire:model="revision_note" type="text" placeholder="Brief note about this version" />
            </x-filament::input.wrapper>
        </x-filament::section>

        <div class="flex justify-end gap-3">
            <x-filament::link href="{{ \App\Filament\App\Resources\LessonPlanFamilyResource::getUrl('index') }}" color="gray">
                Cancel
            </x-filament::link>
            <x-filament::button type="submit">
                Save Lesson Plan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
