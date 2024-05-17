<?php

namespace App\Http\Requests;

class ListExpensesRequest extends BaseRequest
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
            'from_date' => ['sometimes', 'date', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'date', 'date_format:d-m-Y', 'after:from_date'],
            'min_amount' => ['sometimes', 'numeric', 'min:1', "max:" . PHP_INT_MAX],
            'max_amount' => ['sometimes', 'numeric', 'gt:min_amount', "max:" . PHP_INT_MAX],
        ];
    }


    public function attributes(): array
    {
        return [
            'from_date' => __("fields.date"),
            'to_date' => __("fields.date"),
            'min_amount' => __("fields.amount"),
            'max_amount' => __("fields.amount"),
        ];
    }
}
