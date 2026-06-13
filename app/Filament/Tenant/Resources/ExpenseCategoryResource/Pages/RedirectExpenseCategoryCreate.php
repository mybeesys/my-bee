<?php

namespace App\Filament\Tenant\Resources\ExpenseCategoryResource\Pages;

use App\Filament\Tenant\Resources\ExpenseCategoryResource;
use Filament\Resources\Pages\Page;

class RedirectExpenseCategoryCreate extends Page
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected static string $view = 'filament.tenant.pages.tax-profile-create-redirect';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        abort_unless(ExpenseCategoryResource::canCreate(), 403);

        $this->redirect(ExpenseCategoryResource::getUrl('index') . '?create=1');
    }
}
