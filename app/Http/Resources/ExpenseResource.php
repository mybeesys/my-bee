<?php

namespace App\Http\Resources;

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
            'expenseCategoryId' => $this->expense_category_id,
            'expenseCategoryName' => $this->category->name,
            'description' => $this->description,
            'amount' => $this->amount_formatted . " " . main_currency_iso_code(),
            'amountWritten' => numbers_to_words($this->amount),
            'date' => $this->date->format('F j, Y, g:i a'),
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at->format('F j, Y, g:i a'),
            'canDelete' => true,
        ]);
    }
}
