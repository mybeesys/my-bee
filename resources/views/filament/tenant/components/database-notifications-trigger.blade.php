<div class="relative inline-flex tenant-database-notifications-trigger">
    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-bell"
        icon-alias="panels::topbar.open-database-notifications-button"
        icon-size="lg"
        :label="__('filament-panels::layout.actions.open_database_notifications.label')"
        class="fi-topbar-database-notifications-btn"
    />

    @if ($unreadNotificationsCount > 0)
        <span
            aria-hidden="true"
            data-notification-badge="custom"
            class="tenant-notification-count pointer-events-none absolute start-full top-0 z-[2] -translate-x-1/2 -translate-y-1/2 text-sm font-extrabold leading-none text-red-600 rtl:translate-x-1/2 dark:text-red-500"
        >
            {{ $unreadNotificationsCount }}
        </span>
    @endif
</div>
