@php
    $old = $record->changes()->get('old', []);
    $new = $record->changes()->get('attributes', []);
    $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
@endphp

<div class="space-y-3">
    <p class="text-sm text-gray-500">
        {{ class_basename($record->subject_type) }} #{{ $record->subject_id }}
        — {{ $record->event }} by {{ $record->causer?->name ?? 'System / import' }}
        on {{ $record->created_at->format('M j, Y g:ia') }}
    </p>

    @forelse ($fields as $field)
        <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
            <p class="text-xs font-semibold uppercase text-gray-500">{{ $field }}</p>
            <div class="mt-1 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-xs text-gray-400">Before</p>
                    <p class="whitespace-pre-wrap text-red-600 dark:text-red-400">{{ is_array($old[$field] ?? null) ? json_encode($old[$field]) : ($old[$field] ?? '—') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">After</p>
                    <p class="whitespace-pre-wrap text-green-600 dark:text-green-400">{{ is_array($new[$field] ?? null) ? json_encode($new[$field]) : ($new[$field] ?? '—') }}</p>
                </div>
            </div>
        </div>
    @empty
        <p class="text-sm italic text-gray-400">No field-level changes recorded for this entry.</p>
    @endforelse
</div>
