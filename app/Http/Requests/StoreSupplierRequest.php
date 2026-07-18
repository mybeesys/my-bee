<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use App\Rules\ApiUniqueTenantItemRule;
use App\Rules\InternationalPhoneRule;

class StoreSupplierRequest extends BaseRequest
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
            'name' => ['required', 'string', 'max:255', new ApiUniqueTenantItemRule(Supplier::class, 'name')],
            'phone' => ['nullable', new InternationalPhoneRule(false)],
            'address' => ['sometimes', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'state_id' => ['nullable', 'exists:states,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'trn' => ['nullable', 'string', 'max:50'],
            'company' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'string', 'max:255'],
            'notes' => ['sometimes', 'max:1200'],
        ];
    }
}
