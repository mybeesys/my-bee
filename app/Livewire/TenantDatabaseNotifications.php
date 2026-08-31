<?php

namespace App\Livewire;

use Filament\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class TenantDatabaseNotifications extends BaseDatabaseNotifications
{
    public function getNotificationsQuery(): Builder | Relation
    {
        return parent::getNotificationsQuery()->latest('created_at');
    }

    public function getUnreadNotificationsCount(): int
    {
        return (int) parent::getUnreadNotificationsCount();
    }

    public function getTrigger(): View
    {
        $count = $this->getUnreadNotificationsCount();

        return view('filament.tenant.components.database-notifications-trigger', [
            'unreadNotificationsCount' => $count,
            'showNotificationBadge' => $count > 0,
        ]);
    }
}
