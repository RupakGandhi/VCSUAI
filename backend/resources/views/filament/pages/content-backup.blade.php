<x-filament-panels::page>
    <div class="space-y-6">
        <div class="fi-section rounded-xl bg-white p-6 shadow dark:bg-gray-800">
            <h3 class="text-base font-semibold">Export everything</h3>
            <p class="mt-1 text-sm text-gray-500">
                Downloads one JSON file with every strand, course, module (overview, learn,
                facilitator, practice prompts, apply deliverables, simulations — all roles),
                getting-started page, and platform setting. Use this for backups and for the
                portability package owed to VCSU at contract end.
            </p>
            <button
                wire:click="downloadExport"
                type="button"
                class="fi-btn fi-btn-size-md mt-4 inline-flex items-center justify-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-500"
            >
                Download JSON export
            </button>
        </div>

        <div class="fi-section rounded-xl bg-white p-6 shadow dark:bg-gray-800">
            <h3 class="text-base font-semibold">Export as Static Site (ZIP)</h3>
            <p class="mt-1 text-sm text-gray-500">
                Generates a ZIP with everything the client site needs: index.html with all
                CMS content baked in, the uploaded prompt sample files (links rewritten so
                they work offline), and the supporting assets (PDF library, fonts, icons,
                PWA manifest). No API calls, no backend dependency — extract the folder and
                host it anywhere. Survey and usage tracking are disabled in this build.
            </p>
            <p class="mt-2 text-xs text-gray-400">
                The frontend template must exist at
                <code>storage/app/static-template/index.html</code>. When the frontend design
                changes, upload the new index.html there (via file manager or FTP) before exporting.
            </p>
            <a
                href="{{ url('/panel/static-export') }}"
                class="fi-btn fi-btn-size-md mt-4 inline-flex items-center justify-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-500"
            >
                Download Static Site (ZIP)
            </a>
        </div>

        <div class="fi-section rounded-xl bg-white p-6 shadow dark:bg-gray-800">
            <h3 class="text-base font-semibold">Bulk import / content refresh</h3>
            <p class="mt-1 text-sm text-gray-500">
                Upload a JSON file in the same format as the export above. Updates are
                <strong>safe and partial</strong>: only the fields/sections present in the
                file are changed. A file that only updates five modules' Learn content will
                not touch anything else — prompts, deliverables, simulations, and every
                other module are left exactly as they are unless explicitly included.
            </p>

            <form wire:submit.prevent="runImport" class="mt-4 space-y-4">
                {{ $this->form }}
                <button
                    type="submit"
                    class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-500"
                >
                    Run import
                </button>
            </form>
        </div>
    </div>
</x-filament-panels::page>
