<x-filament-panels::page>
    @php
        $module = $this->module;
        $roles = $this->getRoles();
        $roleLabels = [
            'classroom' => 'Classroom Teacher',
            'leader' => 'School/District Leader',
            'sped' => 'Special Education',
            'support' => 'Support Staff',
            'coach' => 'Instructional Coach',
            'higher_ed' => 'Higher Ed Faculty',
        ];
    @endphp

    <div class="space-y-6">
        <div class="fi-section rounded-xl bg-white p-4 shadow dark:bg-gray-800">
            <p class="text-sm text-gray-500">{{ $module->course->strand->title }} &rsaquo; {{ $module->course->title }}</p>
            <h2 class="text-lg font-bold">{{ $module->id }}: {{ $module->title }}</h2>
        </div>

        {{-- EDITABLE: Overview, Practice Prompts, Simulations (incl. verification tips / follow-ups) --}}
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Save Changes
                </x-filament::button>
            </div>
        </form>

        {{-- LEARN (read-only here -- see Edit page for now) --}}
        <div class="fi-section rounded-xl bg-white p-4 shadow dark:bg-gray-800">
            <h3 class="mb-2 text-base font-semibold text-primary-600">Learn</h3>
            <div class="prose prose-sm max-w-none dark:prose-invert">
                {!! $module->content->learn_html ?? '<p class="italic text-gray-400">No learn content imported.</p>' !!}
            </div>
        </div>

        {{-- APPLY (read-only here -- edit via the Apply Deliverables tab on the module's Edit page) --}}
        <div class="fi-section rounded-xl bg-white p-4 shadow dark:bg-gray-800">
            <h3 class="mb-3 text-base font-semibold text-primary-600">Apply Deliverables (by role)</h3>
            <div class="space-y-4">
                @foreach ($roles as $role)
                    @php $deliverables = $module->applyDeliverables->where('role', $role); @endphp
                    @if ($deliverables->isNotEmpty())
                        <details class="rounded border border-gray-200 p-3 dark:border-gray-700" open>
                            <summary class="cursor-pointer text-sm font-semibold">{{ $roleLabels[$role] ?? $role }} ({{ $deliverables->count() }})</summary>
                            <div class="mt-2 space-y-2">
                                @foreach ($deliverables as $deliverable)
                                    <div class="rounded bg-gray-50 p-2 dark:bg-gray-900">
                                        <p class="text-sm font-medium">{{ $deliverable->title }}</p>
                                        <p class="text-xs text-gray-600 dark:text-gray-300">{{ $deliverable->description }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- FACILITATOR (read-only here -- see Edit page for now) --}}
        <div class="fi-section rounded-xl bg-white p-4 shadow dark:bg-gray-800">
            <h3 class="mb-2 text-base font-semibold text-primary-600">Facilitator Guide</h3>
            <div class="prose prose-sm max-w-none dark:prose-invert">
                {!! $module->content->facilitator_html ?? '<p class="italic text-gray-400">No facilitator content imported.</p>' !!}
            </div>
        </div>
    </div>
</x-filament-panels::page>
