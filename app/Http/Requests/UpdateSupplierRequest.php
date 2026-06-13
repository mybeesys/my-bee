<?php

namespace App\Http\Requests;


use App\Models\Supplier;
use App\Rules\ApiUniqueTenantItemRule;
use App\Rules\InternationalPhoneRule;

class UpdateSupplierRequest extends BaseRequest
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
            'name' => ['sometimes', 'string', 'max:255', new ApiUniqueTenantItemRule(Supplier::class, 'name', $id)],
            'phone' => ['sometimes', new InternationalPhoneRule(false)],
            'address' => ['sometimes', 'string', 'max:255'],
            'company' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'string', 'max:255'],
            'notes' => ['sometimes', 'max:1200'],
        ];
    }
}
