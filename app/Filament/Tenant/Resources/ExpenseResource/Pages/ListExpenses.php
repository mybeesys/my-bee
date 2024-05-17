<?php

namespace App\Filament\Tenant\Resources\ExpenseResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;


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

    public function getTabs(): array
    {
        $categories = ExpenseCategory::with(['expenses'])->whereHas('expenses')->get();

        $categories = $categories->sortBy(function ($item) {
            return $item->expenses->sum('amount');
        }, SORT_REGULAR, SORT_ASC);

        $data = [];

        foreach ($categories as $category) {
            $data[$category->name] = Tab::make(str($category->name)->title())
                ->modifyQueryUsing(fn(Builder $query) => $query->where('expense_category_id', $category->id))
                ->badge(Expense::query()->where('expense_category_id', $category->id)->count())
                ->badgeColor('success');
        }
        return array_merge(
            [
                'all' => Tab::make(__('fields.all'))
                    ->badge(Expense::count())
                    ->badgeColor('success')
            ], $data);
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
