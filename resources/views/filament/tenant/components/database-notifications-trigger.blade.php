@php
    $count = (int) ($unreadNotificationsCount ?? 0);
    $showBadge = ($showNotificationBadge ?? false) || $count > 0;
@endphp

<div class="relative inline-flex tenant-database-notifications-trigger" wire:ignore.self>
    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-bell"
        icon-alias="panels::topbar.open-database-notifications-button"
        icon-size="lg"
        :badge="null"
        :label="__('filament-panels::layout.actions.open_database_notifications.label')"
        class="fi-topbar-database-notifications-btn"
    />

    @if ($showBadge)
        <span
            aria-hidden="true"
            data-notification-badge="custom"
            class="tenant-notification-count pointer-events-none absolute start-full top-0 z-[2] min-w-[1.125rem] -translate-x-1/2 -translate-y-1/2 text-center text-base font-black leading-none text-red-600 rtl:translate-x-1/2 dark:text-red-500"
        >
            {{ $count }}
        </span>
    @endif
</div>
