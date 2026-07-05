<?php

namespace App\Livewire;

use Filament\Facades\Filament;
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

    public function getTrigger(): View
    {
        if (Filament::getCurrentPanel()?->getId() === 'tenant') {
            return view('filament.tenant.components.database-notifications-trigger');
        }

        return parent::getTrigger();
    }
}
