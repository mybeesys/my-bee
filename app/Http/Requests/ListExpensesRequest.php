<?php

namespace App\Http\Requests;

class ListExpensesRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'debit_acc4_code' => ['sometimes'],
            'credit_acc4_code' => ['sometimes'],
            'credit_acc4_codes' => ['sometimes', 'array'],
            'credit_acc4_codes.*' => ['string', 'exists:acc4,code'],
            'expense_category_id' => ['sometimes'],
            'expense_category_ids' => ['sometimes', 'array'],
            'expense_category_ids.*' => ['integer', 'exists:expense_categories,id'],
            'date_from' => ['sometimes', 'date'],
            'date_until' => ['sometimes', 'date'],
            'from_date' => ['sometimes', 'date', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'date', 'date_format:d-m-Y', 'after:from_date'],
            'min_amount' => ['sometimes', 'numeric', 'min:1', 'max:'.PHP_INT_MAX],
            'max_amount' => ['sometimes', 'numeric', 'gt:min_amount', 'max:'.PHP_INT_MAX],
            'attachments' => ['sometimes', 'boolean'],
            'include_summaries' => ['sometimes', 'boolean'],
            'paginate' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'in:latest,oldest'],
        ];
    }

    public function attributes(): array
    {
        return [
            'from_date' => __('fields.date'),
            'to_date' => __('fields.date'),
            'min_amount' => __('fields.amount'),
            'max_amount' => __('fields.amount'),
        ];
    }
}
