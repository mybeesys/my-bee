<?php

namespace App\Http\Requests;

class ListPriceOfferRequest extends BaseRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'expiration' => ['sometimes', 'in:active,expired'],
            'from_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'paginate' => ['sometimes'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'in:latest,oldest'],
        ];
    }
}
