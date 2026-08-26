<?php

namespace App\Http\Requests;

class SalesStatementReportRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['sometimes', 'nullable', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'nullable', 'date_format:d-m-Y', 'after_or_equal:from_date'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group_by' => ['sometimes', 'nullable', 'in:product,invoice'],
            'line_types' => ['sometimes', 'nullable', 'array'],
            'line_types.*' => ['in:sale,return'],
            'customer_ids' => ['sometimes', 'nullable', 'array'],
            'customer_ids.*' => ['integer'],
            'product_ids' => ['sometimes', 'nullable', 'array'],
            'product_ids.*' => ['integer'],
        ];
    }
}
