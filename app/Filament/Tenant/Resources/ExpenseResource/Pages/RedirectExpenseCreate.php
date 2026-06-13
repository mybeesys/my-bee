<?php

namespace App\Filament\Tenant\Resources\ExpenseResource\Pages;

use App\Filament\Tenant\Resources\ExpenseResource;
use Filament\Resources\Pages\Page;

class RedirectExpenseCreate extends Page
{
    protected static string $resource = ExpenseResource::class;

    protected static string $view = 'filament.tenant.pages.expense-create-redirect';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        abort_unless(ExpenseResource::canCreate(), 403);

        $this->redirect(ExpenseResource::getUrl('index') . '?create=1');
    }
}
