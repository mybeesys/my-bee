<?php

namespace App\Http\Requests;

class ListProductsMovementsReportRequest extends BaseRequest
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
            'type' => ['sometimes', 'nullable', 'in:purchases,sales,sales_return,purchase_return'],
            'customers' => ['sometimes', 'nullable', 'array'],
            'customers.*' => ['integer'],
            'suppliers' => ['sometimes', 'nullable', 'array'],
            'suppliers.*' => ['integer'],
            'products' => ['sometimes', 'nullable', 'array'],
            'products.*' => ['integer'],
            'invoices' => ['sometimes', 'nullable', 'array'],
            'invoices.*' => ['integer'],
            'invoice_no' => ['sometimes', 'nullable', 'string'],
            'from_date' => ['sometimes', 'nullable', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'nullable', 'date_format:d-m-Y', 'after_or_equal:from_date'],
        ];
    }
}
