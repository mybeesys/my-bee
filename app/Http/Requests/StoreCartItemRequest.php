<?php

namespace App\Http\Requests;

class StoreCartItemRequest extends BaseRequest
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
            'product_id' => ['required', 'integer'],
            'product_type' => ['required', 'in:basic,variants'],
            'product_extras_ids' => ['sometimes', 'array'],
            'qty' => ['sometimes', 'integer', 'min:1'],
            'variants_options_ids' => ['required_if:product_type,==,variants', 'array'],
        ];
    }
}
