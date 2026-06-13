<?php

namespace App\Http\Requests;

use App\Models\ServiceType;
use App\Rules\ApiUniqueTenantItemRule;

class UpdateServiceTypeRequest extends BaseRequest
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
            'name' => ['sometimes', 'max:255', new ApiUniqueTenantItemRule(ServiceType::class, 'name', $id)],
        ];
    }
}
