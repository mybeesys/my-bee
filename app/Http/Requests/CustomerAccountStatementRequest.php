<?php

namespace App\Http\Requests;

class CustomerAccountStatementRequest extends BaseRequest
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
            'from' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
