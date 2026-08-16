<?php

namespace App\Http\Requests;

class ListCustomerOrdersRequest extends BaseRequest
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
            'status' => ['sometimes', 'in:new,packaging,delivery-in-progress,completed,cancelled'],
            'from_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
