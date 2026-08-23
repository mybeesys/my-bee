<?php

namespace App\Http\Requests;

class UpdateOrderRecordRequest extends BaseRequest
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
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'delivery_address' => ['sometimes', 'string', 'max:255'],
            'delivery' => ['sometimes', 'numeric', 'min:0'],
            'payment_method' => ['sometimes', 'in:cash_on_delivery,cash,mbok,fawry,other'],
            'delivery_type' => ['sometimes', 'in:none,delivery,pickup'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2500'],
            'state_id' => ['sometimes', 'nullable', 'integer', 'exists:states,id'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'exists:cities,id'],
            'area_id' => ['sometimes', 'nullable', 'integer', 'exists:areas,id'],
            'details' => ['sometimes', 'array', 'min:1'],
            'details.*.product_id' => ['required_with:details', 'integer', 'exists:products,id'],
            'details.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'details.*.selected_variant_options_ids' => ['sometimes', 'array'],
            'details.*.selected_variant_options_ids.*' => ['integer'],
            'details.*.qty' => ['required_with:details', 'integer', 'min:1', 'max:250000'],
            'details.*.product_extras_ids' => ['sometimes', 'array'],
            'details.*.product_extras_ids.*' => ['integer', 'exists:product_extras,id'],
        ];
    }
}
