@php
    $count = (int) ($unreadNotificationsCount ?? 0);
@endphp

<x-filament::icon-button
    :badge="$count > 0 ? $count : null"
    badge-color="danger"
    color="gray"
    icon="heroicon-o-bell"
    icon-alias="panels::topbar.open-database-notifications-button"
    icon-size="lg"
    :label="__('filament-panels::layout.actions.open_database_notifications.label')"
    class="fi-topbar-database-notifications-btn"
/>
