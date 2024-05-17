<?php

namespace App\Http\Requests;

use App\Rules\InternationalPhoneRule;

class StoreCheckoutRequest extends BaseRequest
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
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'phone' => ['required', new InternationalPhoneRule(false)],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'delivery_address' => ['required', 'string', 'min:1', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'min:1', 'max:255'],
            'payment_method' => ['required', 'in:cash_on_delivery'],
            'notes' => ['sometimes', 'string', 'max:1500'],
        ];
    }
}
