<?php

namespace App\Http\Requests;

class ListSupplierPurchaseInvoicesRequest extends BaseRequest
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
            'status' => ['sometimes', 'in:purchase_order,cancelled,confirmed'],
            'from_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
