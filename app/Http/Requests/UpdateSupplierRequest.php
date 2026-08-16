<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPartyContactLocation;
use App\Models\Supplier;
use App\Rules\ApiUniqueTenantItemRule;
use App\Rules\InternationalPhoneRule;

class UpdateSupplierRequest extends BaseRequest
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
        $id = $this->route('supplier') ?? $this->route('id');

        return array_merge([
            'name' => ['sometimes', 'string', 'max:255', new ApiUniqueTenantItemRule(Supplier::class, 'name', $id)],
            'phone' => ['sometimes', 'nullable', new InternationalPhoneRule(false)],
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
