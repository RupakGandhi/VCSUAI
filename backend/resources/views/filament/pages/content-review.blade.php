<x-filament-panels::page>
    <div class="space-y-6">
        <div class="fi-section rounded-xl bg-white p-6 shadow dark:bg-gray-800">
            <h3 class="text-base font-semibold">Export for review</h3>
            <p class="mt-1 text-sm text-gray-500">
                Downloads a plain-text file with every module's Challenges (by role), Practice
                Prompts, Mastery Prompts, and Deliverables (with Step-by-Step Guide prompts) for
                the client you're currently managing. Read it, edit it directly, or run it
                through an AI tool and check its output here before anything is imported.
            </p>
            <button
                wire:click="downloadExport"
                type="button"
                class="fi-btn fi-btn-size-md mt-4 inline-flex items-center justify-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-500"
            >
                Download review file
            </button>
        </div>

        <div class="fi-section rounded-xl bg-white p-6 shadow dark:bg-gray-800">
            <h3 class="text-base font-semibold">Review &amp; import</h3>
            <p class="mt-1 text-sm text-gray-500">
                Upload the edited file, click <strong>Preview Changes</strong> to see exactly
                what would change (nothing is written yet), then <strong>Apply Changes</strong>
                once it looks right.
            </p>

            <form wire:submit.prevent="previewChanges" class="mt-4 space-y-4">
                {{ $this->form }}
                <button
                    type="submit"
                    class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-500"
                >
                    Preview Changes
                </button>
            </form>
        </div>

        @if ($validationErrors)
            <div class="fi-section rounded-xl p-6 shadow" style="background-color:#fef2f2;border:1px solid #fecaca">
                <h3 class="text-base font-semibold" style="color:#991b1b">
                    ⚠ Cannot preview this file -- {{ count($validationErrors) }} problem(s) found
                </h3>
                <p class="mt-1 text-sm" style="color:#7f1d1d">
                    Nothing has been changed. Fix these in the file and re-upload.
                </p>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm" style="color:#7f1d1d">
                    @foreach ($validationErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($diffResult)
            <div class="fi-section rounded-xl bg-white p-6 shadow dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold">Preview</h3>
                    <button
                        wire:click="applyChanges"
                        wire:confirm="Apply these changes to the currently-managed client's database?"
                        type="button"
                        class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-1 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow"
                        style="background-color:#dc2626"
                        onmouseover="this.style.backgroundColor='#b91c1c'" onmouseout="this.style.backgroundColor='#dc2626'"
                    >
                        Apply Changes
                    </button>
                </div>

                {{-- Inline styles throughout this diff panel on purpose: this project's
                     compiled Filament CSS only includes Filament's own branded utility
                     classes (primary-*, danger-*) plus a few base grays -- plain Tailwind
                     accent colors (blue-*, green-*, red-*, yellow-*) aren't in the bundle
                     at all and silently render as no-ops (confirmed: a bg-blue-50/border-
                     blue-200 box rendered fully transparent with a default gray border).
                     Inline styles sidestep that entirely. --}}
                <div class="mt-3 flex gap-4 text-sm">
                    <span style="color:#71717a">Unchanged: {{ $diffResult['summary']['unchanged'] }}</span>
                    <span style="color:#2563eb;font-weight:600">Updated: {{ $diffResult['summary']['updated'] }}</span>
                    <span style="color:#16a34a;font-weight:600">Created: {{ $diffResult['summary']['created'] }}</span>
                    <span style="color:#dc2626;font-weight:600">Deleted: {{ $diffResult['summary']['deleted'] }}</span>
                </div>

                @if (!empty($diffResult['modulesSkipped']))
                    <p class="mt-3 rounded-lg p-3 text-sm" style="background-color:#fefce8;color:#854d0e">
                        <strong>Modules not in this client (skipped):</strong> {{ implode(', ', $diffResult['modulesSkipped']) }}
                    </p>
                @endif

                @if (empty($diffResult['changes']))
                    <p class="mt-4 text-sm" style="color:#71717a">No changes -- the uploaded file matches the current content exactly.</p>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach ($diffResult['changes'] as $change)
                            @php
                                $colors = match ($change['action']) {
                                    'updated' => ['bg' => '#eff6ff', 'border' => '#bfdbfe'],
                                    'created' => ['bg' => '#f0fdf4', 'border' => '#bbf7d0'],
                                    'deleted' => ['bg' => '#fef2f2', 'border' => '#fecaca'],
                                    default => ['bg' => '#fafafa', 'border' => '#e4e4e7'],
                                };
                            @endphp
                            <div class="rounded-lg border p-3 text-sm" style="background-color:{{ $colors['bg'] }};border-color:{{ $colors['border'] }}">
                                <div class="font-medium">
                                    {{ strtoupper($change['action']) }} —
                                    {{ $change['moduleId'] }} / {{ $change['role'] }} / {{ $change['type'] }}
                                    @if (!is_null($change['index'])) #{{ $change['index'] + 1 }} @endif
                                </div>
                                @if ($change['old'])
                                    <div class="mt-1" style="color:#71717a"><span class="font-medium">Before:</span> {{ is_array($change['old']) ? json_encode($change['old']) : $change['old'] }}</div>
                                @endif
                                @if ($change['new'])
                                    <div class="mt-1" style="color:#3f3f46"><span class="font-medium">After:</span> {{ is_array($change['new']) ? json_encode($change['new']) : $change['new'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
