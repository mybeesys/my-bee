<?php

namespace App\Http\Requests;

class UpdateSupplyOrderRequest extends BaseRequest
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
            'supplier_id' => ['sometimes', 'integer', 'exists:suppliers,id'],
            'description' => ['sometimes', 'string', 'max:255'],
            'details' => ['sometimes', 'array', 'min:1'],
            'details.*.product_id' => ['required_with:details', 'integer', 'exists:products,id'],
            'details.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'details.*.selected_variant_options_ids' => ['sometimes', 'array'],
            'details.*.selected_variant_options_ids.*' => ['integer'],
            'details.*.qty' => ['required_with:details', 'integer', 'min:1', 'max:250000'],
        ];
    }
}
