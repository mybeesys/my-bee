<?php

namespace App\Http\Requests;

class ListCashDetReportRequest extends BaseRequest
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
            'account_code' => ['sometimes', 'nullable', 'string'],
            'op_id' => ['sometimes', 'nullable', 'integer'],
            'transaction_id' => ['sometimes', 'nullable', 'string'],
            'from_date' => ['sometimes', 'nullable', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'nullable', 'date_format:d-m-Y', 'after_or_equal:from_date'],
        ];
    }
}
