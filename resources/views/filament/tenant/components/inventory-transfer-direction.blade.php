@php
    $record = $getRecord();
    $direction = $record->transfer_direction ?? 'out';
    $label = $record->transfer_direction_label ?? '—';
@endphp

<span class="inventory-report__cell-with-icon inventory-report__cell-with-icon--{{ $direction }}">
    <span class="inventory-report__icon-box" aria-hidden="true">
        <svg class="inventory-report__icon" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            @if ($direction === 'out')
                <path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .75.75v10.638l3.96-3.96a.75.75 0 1 1 1.06 1.06l-5.25 5.25a.75.75 0 0 1-1.06 0l-5.25-5.25a.75.75 0 1 1 1.06-1.06l3.96 3.96V3.75A.75.75 0 0 1 10 3z" clip-rule="evenodd" />
            @else
                <path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.612l-3.96 3.96a.75.75 0 1 1-1.06-1.06l5.25-5.25a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 1 1-1.06 1.06l-3.96-3.96V16.25A.75.75 0 0 1 10 17z" clip-rule="evenodd" />
            @endif
        </svg>
    </span>
    <span class="inventory-report__direction-label">{{ $label }}</span>
</span>
