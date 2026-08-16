<?php

namespace App\Http\Requests;

class StoreSupplyOrderRequest extends BaseRequest
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
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'description' => ['required', 'string', 'max:255'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'details.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'details.*.qty' => ['required', 'integer', 'min:1', 'max:250000'],
        ];
    }
}
