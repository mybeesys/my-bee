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
            'amount' => number_format($this->amount, currency_decimals(), '.', ''),
            'amountFormatted' => $this->amount_formatted . " " . main_currency_iso_code(),
            'tax' => number_format($this->tax, currency_decimals(), '.', ''),
            'taxFormatted' => $this->tax_formatted . " " . main_currency_iso_code(),
            'amountWritten' => numbers_to_words($this->amount),
            'taxWritten' => numbers_to_words($this->tax ?? 0),
            'taxProfile' => new TaxProfileResource($this->taxProfile),
            'totalFormatted' => number_format($this->total, currency_decimals(), '.', '') . " " . main_currency_iso_code(),
            'totalWritten' => numbers_to_words($this->total),
            'debitAccount' => new Acc4Resource($this->whenLoaded('debitAccount')),
            'creditAccount' => new Acc4Resource($this->whenLoaded('creditAccount')),
            'date' => $this->date->format('d-m-Y'),
            'dateFormatted' => $this->date->format('F j, Y'),
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at ? $this->updated_at->format('F j, Y, g:i a') : null,
            'canDelete' => true,
        ]);
    }
}
