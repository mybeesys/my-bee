<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PreviewExpenseTaxRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount_includes_tax')) {
            $this->merge([
                'amount_includes_tax' => filter_var($this->input('amount_includes_tax'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $includesTax = (bool) $this->input('amount_includes_tax');

        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:'.PHP_INT_MAX],
            'amount_includes_tax' => ['sometimes', 'boolean'],
            'tax_profile_id' => [
                Rule::requiredIf($includesTax),
                'nullable',
                'exists:tax_profiles,id',
            ],
        ];
    }
}
