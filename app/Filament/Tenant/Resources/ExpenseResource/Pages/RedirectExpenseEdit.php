<?php

namespace App\Filament\Tenant\Resources\ExpenseResource\Pages;

use App\Filament\Tenant\Resources\ExpenseResource;
use App\Models\Expense;
use Filament\Resources\Pages\Page;

class RedirectExpenseEdit extends Page
{
    protected static string $resource = ExpenseResource::class;

    protected static string $view = 'filament.tenant.pages.expense-create-redirect';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(int | string $record): void
    {
        $expense = Expense::query()->find($record);

        abort_unless($expense && ExpenseResource::canEdit($expense), 403);

        $this->redirect(ExpenseResource::getExpenseEditUrl($expense));
    }
}
