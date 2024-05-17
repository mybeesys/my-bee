<?php

namespace App\Http\Requests;

use App\Models\Tax;
use App\Models\TaxProfile;
use App\Rules\ApiUniqueTenantItemRule;

class UpdateTaxProfileRequest extends BaseRequest
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
            'name' => ['sometimes', 'max:255', new ApiUniqueTenantItemRule(TaxProfile::class, 'name', $id)],
            'taxes' => ['required', 'array', 'min:1'],
            'taxes.*.id' => ['sometimes', 'exists:taxes,id'],
            'taxes.*.description' => ['sometimes', 'string'],
            'taxes.*.percent' => ['sometimes', 'numeric', 'min:1', 'max:100'],
        ];
    }
}
