<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPartyContactLocation;
use App\Models\Supplier;
use App\Rules\ApiUniqueTenantItemRule;
use App\Rules\InternationalPhoneRule;

class StoreSupplierRequest extends BaseRequest
{
    use ValidatesPartyContactLocation;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255', new ApiUniqueTenantItemRule(Supplier::class, 'name')],
            'phone' => ['nullable', new InternationalPhoneRule(false)],
            'email' => ['nullable', 'email', 'max:255'],
            'trn' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1200'],
        ], $this->partyContactLocationRules());
    }
}
