<?php

namespace App\Http\Requests;

use App\Models\Warehouse;
use App\Rules\ApiUniqueTenantItemRule;
use App\Rules\InternationalPhoneRule;
use App\Rules\UniqueTenantItemRule;

class UpdateWarehouseRequest extends BaseRequest
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
            'name' => ['sometimes', 'max:255', new ApiUniqueTenantItemRule(Warehouse::class, 'name', $id)],
            'phone' => ['sometimes', new InternationalPhoneRule(false)],
            'address' => ['sometimes', 'max:255'],
            'description' => ['sometimes', 'max:255'],
        ];
    }
}
