<?php

namespace App\Filament\Tenant\Resources\ExpenseResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\CreateAction as TableCreateAction;
use Filament\Tables\Actions\EditAction as TableEditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    public function mount(): void
    {
        parent::mount();

        if (request()->query('create')) {
            $this->mountAction('create');
        }

        if ($editId = request()->query('edit')) {
            $expense = Expense::query()->find($editId);

            if ($expense && ExpenseResource::canEdit($expense)) {
                $this->mountTableAction('edit', $expense);
            }
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ExpenseResource\Widgets\ExpensesOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            AddToFavourites::make('fav')
                ->settingKey('fav.expenses'),
            Actions\CreateAction::make(),
        ];
    }

    protected function configureCreateAction(CreateAction | TableCreateAction $action): void
    {
        parent::configureCreateAction($action);

        if (! $action instanceof CreateAction) {
            return;
        }

        $action
            ->slideOver()
            ->modalWidth(MaxWidth::FiveExtraLarge)
            ->createAnother(false)
            ->using(fn (array $data): Model => ExpenseResource::createExpenseRecord($data))
            ->after(fn (Expense $record) => ExpenseResource::postExpenseCreated($record));
    }

    protected function configureEditAction(TableEditAction $action): void
    {
        parent::configureEditAction($action);

        $action
            ->slideOver()
            ->modalWidth(MaxWidth::FiveExtraLarge)
            ->mutateRecordDataUsing(fn (array $data): array => ExpenseResource::mutateExpenseEditData($data))
            ->mutateFormDataUsing(fn (array $data): array => ExpenseResource::mutateExpenseCreateData($data));
    }

    public function getTabs(): array
    {
        $categories = ExpenseCategory::with(['expenses'])->whereHas('expenses')->get();

        $categories = $categories->sortBy(function ($item) {
            return $item->expenses->sum('amount');
        }, SORT_REGULAR, SORT_ASC);

        $data = [];

        foreach ($categories as $category) {
            $data[$category->name] = Tab::make(str($category->name)->title())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('expense_category_id', $category->id))
                ->badge(Expense::query()->where('expense_category_id', $category->id)->count())
                ->badgeColor('success');
        }

        return array_merge(
            [
                'all' => Tab::make(__('fields.all'))
                    ->badge(Expense::count())
                    ->badgeColor('success'),
            ],
            $data
        );
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
