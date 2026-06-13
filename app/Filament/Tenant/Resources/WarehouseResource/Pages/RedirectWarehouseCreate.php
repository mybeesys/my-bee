<?php

namespace App\Filament\Tenant\Resources\WarehouseResource\Pages;

use App\Filament\Tenant\Resources\WarehouseResource;
use Filament\Resources\Pages\Page;

class RedirectWarehouseCreate extends Page
{
    protected static string $resource = WarehouseResource::class;

    protected static string $view = 'filament.tenant.pages.tax-profile-create-redirect';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        abort_unless(WarehouseResource::canCreate(), 403);

        $this->redirect(WarehouseResource::getUrl('index') . '?create=1');
    }
}
