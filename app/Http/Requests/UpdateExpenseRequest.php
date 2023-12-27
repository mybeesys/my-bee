<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Rules\ApiUniqueTenantItemRule;

class UpdateExpenseRequest extends BaseRequest
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
        $id = str(request()->getRequestUri())->afterLast('/')->value();
        return [
            'description' => ['sometimes'],
            'amount' => ['sometimes', 'numeric', "max:". PHP_INT_MAX],
            'date' => ['sometimes', 'date', 'date_format:d-m-Y'],
            'expense_category_id' => ['sometimes', 'exists:expense_categories,id'],
        ];
    }
}
