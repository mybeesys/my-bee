@php
    $tones = [
        'sky' => 'settings-hub__icon--sky',
        'violet' => 'settings-hub__icon--violet',
        'emerald' => 'settings-hub__icon--emerald',
        'amber' => 'settings-hub__icon--amber',
        'rose' => 'settings-hub__icon--rose',
        'indigo' => 'settings-hub__icon--indigo',
        'teal' => 'settings-hub__icon--teal',
        'slate' => 'settings-hub__icon--slate',
    ];
    $toneClass = $tones[$tone ?? 'slate'] ?? $tones['slate'];
@endphp

<a
    href="{{ $url }}"
    wire:navigate
    class="settings-hub__card group"
>
    <span class="settings-hub__icon {{ $toneClass }}" aria-hidden="true">
        <x-filament::icon
            :icon="$icon"
            class="settings-hub__icon-svg"
        />
    </span>

    <span class="settings-hub__card-body">
        <span class="settings-hub__card-title">{{ $title }}</span>
    </span>

    <x-filament::icon
        icon="heroicon-m-chevron-left"
        class="settings-hub__card-arrow"
    />
</a>
