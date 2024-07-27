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
            'amount' => ['required', 'numeric', "max:". PHP_INT_MAX],
            'date' => ['required', 'date', 'date_format:d-m-Y'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'tax_profile_id' => ['nullable', 'exists:tax_profiles,id'],
            'credit_acc4_code' => ['required', 'exists:acc4,code'],
            'debit_acc4_code' => ['required', 'exists:acc4,code'],
            'description' => ['required'],
        ];
    }
}
