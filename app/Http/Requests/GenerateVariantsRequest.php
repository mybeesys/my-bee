<?php

namespace App\Http\Requests;

class GenerateVariantsRequest extends BaseRequest
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
            'variantLibraries' => ['required', 'array', 'min:1'],
            'variantLibraries.*.id' => ['required', 'exists:variant_libraries,id'],
            'variantLibraries.*.selectedOptions' => ['required', 'array', 'min:1'],

            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_name' => ['nullable', 'string'],
        ];
    }
}
