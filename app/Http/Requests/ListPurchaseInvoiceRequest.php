<?php

namespace App\Http\Requests;

use Carbon\Carbon;

class ListPurchaseInvoiceRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['date', 'from_date', 'to_date'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $this->merge([
                    $field => Carbon::createFromFormat('Y-m-d', $value)->format('d-m-Y'),
                ]);
            }
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:purchase_order,cancelled,confirmed'],
            'payment_status' => ['sometimes', 'in:Post paid,Partly paid,Paid'],
            'payment_terms' => ['sometimes', 'in:cash,credit'],
            'payment_method' => ['sometimes', 'string'],
            'transaction_ref' => ['sometimes', 'string'],
            'warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
            'discount_method' => ['sometimes', 'in:amount,percent,none'],
            'supplier_id' => ['sometimes', 'integer', 'exists:suppliers,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date' => ['sometimes', 'date', 'date_format:d-m-Y'],
            'from_date' => ['sometimes', 'date', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'date', 'date_format:d-m-Y', 'after:from_date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'paginate' => ['sometimes'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'in:latest,oldest'],
        ];
    }
}
