<?php

namespace App\Http\Requests;

class UpdateSalesReturnsRequest extends BaseRequest
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
            'notes' => ['sometimes'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:invoice_items,id'],
            'items.*.qty' => ['required', 'integer']
        ];
    }
}
