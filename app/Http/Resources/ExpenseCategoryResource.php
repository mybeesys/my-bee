<?php

namespace App\Http\Resources;

class ExpenseCategoryResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $expensesCount = $this->relationLoaded('expenses')
            ? $this->expenses->count()
            : (int) ($this->expenses_count ?? 0);

        $expensesTotal = $this->relationLoaded('expenses')
            ? (float) $this->expenses->sum('amount')
            : (float) ($this->expenses_total ?? 0);

        return $this->filterFields([
            'id' => $this->id,
            'name' => $this->name,
            'expensesCount' => $expensesCount,
            'expensesTotal' => format_amount($expensesTotal),
            'expensesTotalNumeric' => round($expensesTotal, currency_decimals()),
            'expensesTotalFormatted' => main_currency_iso_code().' '.format_amount($expensesTotal),
            'expenses' => $this->when(
                $this->relationLoaded('expenses') && request()->boolean('include_expenses', true),
                fn () => ExpenseResource::collection($this->expenses)
            ),
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at ? $this->updated_at->format('F j, Y, g:i a') : null,
            'actions' => [
                'canEdit' => true,
                'canDelete' => $expensesCount === 0,
            ],
            'canDelete' => $expensesCount === 0,
        ]);
    }
}
