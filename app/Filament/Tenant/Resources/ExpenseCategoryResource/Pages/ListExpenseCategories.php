<?php

namespace App\Filament\Tenant\Resources\ExpenseCategoryResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Pages\CustomSettings;
use App\Filament\Tenant\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Form;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\CreateAction as TableCreateAction;
use Filament\Tables\Actions\EditAction as TableEditAction;

class ListExpenseCategories extends ListRecords
{
    protected static string $resource = ExpenseCategoryResource::class;

    public function mount(): void
    {
        parent::mount();

        if (request()->query('create')) {
            $this->mountAction('create');
        }

        if ($editId = request()->query('edit')) {
            $expenseCategory = ExpenseCategory::query()->find($editId);

            if ($expenseCategory && ExpenseCategoryResource::canEdit($expenseCategory)) {
                $this->mountTableAction('edit', $expenseCategory);
            }
        }
    }

    public function getBreadcrumbs(): array
    {
        return array_merge([
            CustomSettings::getUrl() => __('fields.settings'),
        ], parent::getBreadcrumbs());
    }

    protected function getHeaderActions(): array
    {
        return [
            AddToFavourites::make('fav')
                ->settingKey('fav.expenses_categories'),
            Actions\CreateAction::make(),
            Action::make('back')
                ->icon('heroicon-m-arrow-uturn-left')
                ->size(ActionSize::Large)
                ->url(CustomSettings::getUrl())
                ->iconButton(),
        ];
    }

    protected function configureCreateAction(CreateAction | TableCreateAction $action): void
    {
        parent::configureCreateAction($action);

        if (! $action instanceof CreateAction) {
            return;
        }

        $action
            ->label(__('fields.add_expense_category'))
            ->form(fn (Form $form): Form => $this->form($form->columns(1)))
            ->slideOver()
            ->modalWidth(MaxWidth::TwoExtraLarge)
            ->createAnother(false);
    }

    protected function configureEditAction(TableEditAction $action): void
    {
        parent::configureEditAction($action);

        $action
            ->form(fn (Form $form): Form => $this->form($form->columns(1)))
            ->slideOver()
            ->modalWidth(MaxWidth::TwoExtraLarge);
    }
}
