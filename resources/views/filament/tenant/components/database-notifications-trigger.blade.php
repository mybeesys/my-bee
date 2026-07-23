<div class="relative inline-flex tenant-database-notifications-trigger">
    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-bell"
        icon-alias="panels::topbar.open-database-notifications-button"
        icon-size="lg"
        :label="__('filament-panels::layout.actions.open_database_notifications.label')"
        class="fi-topbar-database-notifications-btn"
    />

    @if (filled($unreadNotificationsCount))
        <span
            aria-hidden="true"
            data-notification-badge="custom"
            class="pointer-events-none absolute start-full top-1 z-[2] flex min-h-[1.125rem] min-w-[1.125rem] -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full px-1 text-[11px] font-bold leading-none text-white rtl:translate-x-1/2"
            style="background-color:#dc2626;border:2px solid #ffffff;box-shadow:0 2px 8px rgba(220,38,38,.5);color:#ffffff;"
        >
            {{ $unreadNotificationsCount }}
        </span>
    @endif
</div>
