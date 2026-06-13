<?php

namespace App\Http\Requests;

use App\Models\Tax;
use App\Models\TaxProfile;
use App\Rules\ApiUniqueTenantItemRule;

class StoreTaxProfileRequest extends BaseRequest
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
            'name' => ['required', 'max:255', new ApiUniqueTenantItemRule(TaxProfile::class, 'name')],
            'taxes' => ['required', 'array', 'min:1'],
            'taxes.*.description' => ['required', new ApiUniqueTenantItemRule(Tax::class, 'description')],
            'taxes.*.percent' => ['required', 'min:1', 'max:100'],
        ];
    }
}
