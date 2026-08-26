<?php

namespace App\Http\Requests;

class ListPurchasesReturnsRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'invoice_no' => ['sometimes', 'nullable', 'string'],
            'from_date' => ['sometimes', 'nullable', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'nullable', 'date_format:d-m-Y', 'after_or_equal:from_date'],
            'q' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
