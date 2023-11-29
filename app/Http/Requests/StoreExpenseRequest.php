<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Rules\ApiUniqueTenantItemRule;

class StoreExpenseRequest extends BaseRequest
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
            'name' => ['required', 'max:255', new ApiUniqueTenantItemRule(Expense::class, 'name')],
            'amount' => ['required', 'numeric', "max:". PHP_INT_MAX],
            'date' => ['required', 'date', 'date_format:d-m-Y'],
            'description' => ['required'],
        ];
    }
}
