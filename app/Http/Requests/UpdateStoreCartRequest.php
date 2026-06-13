<?php

namespace App\Http\Requests;

class UpdateStoreCartRequest extends BaseRequest
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
            'id' => ['required'],
            'qty' => ['sometimes', 'integer'],
            'product_extras_ids_to_add' => ['sometimes', 'array'],
        ];
    }
}
