<?php

namespace App\Filament\Tenant\Resources\ExpenseResource\Widgets;


use App\Models\ExpenseCategory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpensesOverview extends BaseWidget
{

    protected function getStats(): array
    {
        $cards = [];

        $categories = ExpenseCategory::with(['expenses'])->whereHas('expenses')->get();

        $categories = $categories->sortBy(function ($item) {
            return $item->expenses->sum('total');
        }, SORT_REGULAR, SORT_ASC);

        foreach ($categories as $category) {
            $amount = $category->expenses->sum('total');
            $amount_words = numbers_to_words($amount);
            $amount_formatted = main_currency_iso_code() . " " . format_amount($amount);
            $cards[] = Stat::make($category->name, $amount_formatted)->description($amount_words)->color('warning');
        }

        $total_amount = $categories->pluck('expenses')->flatten()->sum('total');
        $total_formatted = main_currency_iso_code() . " " .format_amount($total_amount);
        $total_words = numbers_to_words($total_amount);

        return array_merge($cards, [
            Stat::make(__('fields.total'), $total_formatted)
                ->description($total_words)
                ->color('success')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'wire:click' => "\$dispatch('setStatusFilter', { filter: 'processed' })",
                ])
        ]);
    }
}
