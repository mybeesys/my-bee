<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ExpenseResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'name' => $this->name,
            'expenseCategoryId' => $this->expense_category_id,
            'expenseCategory' => $this->category->name,
            'amount' => main_currency_iso_code() . " " .$this->amount_formatted,
            'amountWritten' => numbers_to_words($this->amount),
            'date' => $this->date->format('F j, Y, g:i a'),
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at->format('F j, Y, g:i a'),
            'canDelete' => true,
        ]);
    }
}
