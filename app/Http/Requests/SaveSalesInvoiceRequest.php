<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSalesInvoiceRequest extends BaseRequest
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
            'no' => ['required', 'string'],
            'uid' => ['required', 'exists:invoices,uid'],
            'date' => ['required', 'date', 'date_format:d-m-Y'],
            'customer_id' => ['required', 'exists:customers,id'],
        ];
    }
}
