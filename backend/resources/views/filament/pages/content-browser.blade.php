<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm" style="color:#71717a">
            Everything currently live for the client you're managing, grouped Module → Role, so
            you can check that the Challenge, Practice Prompts, Mastery Prompts, and Deliverables
            for a given module/role read as one cohesive whole. Read-only -- to change anything,
            use <strong>Content Review Export/Import</strong>.
        </p>

        @foreach ($modules as $module)
            <details class="fi-section overflow-hidden rounded-xl bg-white shadow dark:bg-gray-800">
                <summary class="cursor-pointer select-none p-4 text-base font-semibold">
                    {{ $module['id'] }}: {{ $module['title'] }}
                </summary>

                <div class="space-y-6 border-t p-4" style="border-color:#e4e4e7">
                    @foreach ($module['roles'] as $roleKey => $role)
                        <div class="rounded-lg p-4" style="background-color:#fafafa;border:1px solid #e4e4e7">
                            <h4 class="text-sm font-semibold" style="color:#18181b">{{ $role['label'] }}</h4>

                            <div class="mt-3">
                                <div class="text-xs font-semibold uppercase tracking-wide" style="color:#a1a1aa">Challenge</div>
                                <p class="mt-1 text-sm" style="color:{{ $role['challenge'] ? '#3f3f46' : '#a1a1aa' }}">
                                    {{ $role['challenge'] ?? '(none)' }}
                                </p>
                            </div>

                            <div class="mt-4">
                                <div class="text-xs font-semibold uppercase tracking-wide" style="color:#a1a1aa">
                                    Practice Prompts ({{ count($role['practicePrompts']) }})
                                </div>
                                @forelse ($role['practicePrompts'] as $i => $p)
                                    <div class="mt-2 rounded-md p-2 text-sm" style="background-color:#ffffff;border:1px solid #f4f4f5">
                                        <div class="font-medium">{{ $i + 1 }}. {{ $p['title'] }}@if ($p['aiTool']) <span style="color:#a1a1aa">— {{ $p['aiTool'] }}</span>@endif</div>
                                        <div class="mt-1" style="color:#52525b">{{ $p['promptText'] }}</div>
                                    </div>
                                @empty
                                    <p class="mt-1 text-sm" style="color:#a1a1aa">(none)</p>
                                @endforelse
                            </div>

                            <div class="mt-4">
                                <div class="text-xs font-semibold uppercase tracking-wide" style="color:#a1a1aa">
                                    Mastery Prompts ({{ count($role['masteryPrompts']) }})
                                </div>
                                @forelse ($role['masteryPrompts'] as $i => $p)
                                    <div class="mt-2 rounded-md p-2 text-sm" style="background-color:#ffffff;border:1px solid #f4f4f5">
                                        <div class="font-medium">{{ $i + 1 }}. {{ $p['title'] }}</div>
                                        <div class="mt-1" style="color:#52525b">{{ $p['promptText'] }}</div>
                                    </div>
                                @empty
                                    <p class="mt-1 text-sm" style="color:#a1a1aa">(none)</p>
                                @endforelse
                            </div>

                            <div class="mt-4">
                                <div class="text-xs font-semibold uppercase tracking-wide" style="color:#a1a1aa">
                                    Deliverables ({{ count($role['deliverables']) }})
                                </div>
                                @forelse ($role['deliverables'] as $i => $d)
                                    <div class="mt-2 rounded-md p-2 text-sm" style="background-color:#ffffff;border:1px solid #f4f4f5">
                                        <div class="font-medium">{{ $i + 1 }}. {{ $d['description'] }}</div>
                                        <div class="mt-1" style="color:#52525b">
                                            <span class="font-medium">Initial:</span>
                                            {{ $d['initialPrompt'] ?? '(auto-generated from description)' }}
                                        </div>
                                        <div class="mt-1" style="color:#52525b">
                                            <span class="font-medium">Refine:</span>
                                            {{ $d['refinePrompt'] ?? '(auto-generated from description)' }}
                                        </div>
                                    </div>
                                @empty
                                    <p class="mt-1 text-sm" style="color:#a1a1aa">(none)</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endforeach
    </div>
</x-filament-panels::page>
