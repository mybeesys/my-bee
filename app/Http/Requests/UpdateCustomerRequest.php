<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Rules\ApiUniqueTenantItemRule;
use App\Rules\InternationalPhoneRule;

class UpdateCustomerRequest extends BaseRequest
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
            'name' => ['sometimes', 'string', 'max:255', new ApiUniqueTenantItemRule(Customer::class, 'name', $id)],
            'phone' => ['sometimes', new InternationalPhoneRule(false), new ApiUniqueTenantItemRule(Customer::class, 'phone', $id)],
            'email' => ['sometimes', 'email', 'max:255'],
            'trn' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'state_id' => ['nullable', 'exists:states,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
