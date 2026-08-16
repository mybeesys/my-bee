<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPartyContactLocation;
use App\Models\Customer;
use App\Rules\ApiUniqueTenantItemRule;
use App\Rules\InternationalPhoneRule;

class StoreCustomerRequest extends BaseRequest
{
    use ValidatesPartyContactLocation;

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
        return array_merge([
            'name' => ['required', 'string', 'max:255', new ApiUniqueTenantItemRule(Customer::class, 'name')],
            'phone' => $this->phoneRules(),
            'email' => ['nullable', 'email', 'max:255'],
            'trn' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
        ], $this->partyContactLocationRules());
    }

    protected function phoneRules(): array
    {
        $rules = ['nullable', new InternationalPhoneRule(false)];

        if (filled($this->input('phone'))) {
            $rules[] = new ApiUniqueTenantItemRule(Customer::class, 'phone');
        }

        return $rules;
    }
}
