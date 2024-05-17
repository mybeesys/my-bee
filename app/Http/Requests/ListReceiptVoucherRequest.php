<?php

namespace App\Http\Requests;

class ListReceiptVoucherRequest extends BaseRequest
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
            'for' => ['sometimes', 'in:customer,other_entity'],
            'invoice_id' => ['sometimes', 'integer'],
            'acc4_code' => ['sometimes', 'string'],
            'date' => ['sometimes', 'date', 'date_format:d-m-Y'],
            'from_date' => ['sometimes', 'date', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'date', 'date_format:d-m-Y', 'after:from_date'],
            'sort' => ['sometimes', 'in:latest,oldest'],
        ];
    }
}
