<?php

namespace App\Filament\Tenant\Resources\ExpenseCategoryResource\Pages;

use App\Filament\Tenant\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use Filament\Resources\Pages\Page;

class RedirectExpenseCategoryEdit extends Page
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected static string $view = 'filament.tenant.pages.tax-profile-create-redirect';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(int | string $record): void
    {
        $expenseCategory = ExpenseCategory::query()->find($record);

        abort_unless($expenseCategory && ExpenseCategoryResource::canEdit($expenseCategory), 403);

        $this->redirect(ExpenseCategoryResource::getExpenseCategoryEditUrl($expenseCategory));
    }
}
