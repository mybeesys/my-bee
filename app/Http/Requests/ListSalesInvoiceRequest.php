<?php

namespace App\Http\Requests;

class ListSalesInvoiceRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:sale_order,cancelled,confirmed'],
            'payment_status' => ['sometimes', 'in:Post paid,Partly paid,Paid'],
            'payment_method' => ['sometimes', 'string'],
            'transaction_ref' => ['sometimes', 'string'],
            'discount_method' => ['sometimes', 'in:amount,percent,none'],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'user_id' => ['sometimes', 'integer','exists:users,id'],
            'date' => ['sometimes', 'date', 'date_format:d-m-Y'],
            'from_date' => ['sometimes', 'date', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'date', 'date_format:d-m-Y', 'after:from_date'],
            'sort' => ['sometimes', 'in:latest,oldest'],
        ];
    }
}
