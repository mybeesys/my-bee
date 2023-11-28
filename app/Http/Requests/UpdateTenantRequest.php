<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\ApiTenantAttributeRule;
use App\Rules\InternationalPhoneRule;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('sanctum')->user()->hasRole(User::ROLE_CLIENT);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant_id = request()->header('Tenant-Id');
//        dd('sometimes', 'string', 'max:255', 'unique:tenants,name,'.request()->header('Tenant-Id').',id');
        //'unique:tenants,name,'.request()->header('Tenant-Id').',id'
        return [
            'type' => ['sometimes', 'string', 'in:company,individual'],
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('tenants', 'name')->ignore($tenant_id)],
            'phone' => ['sometimes', new ApiTenantAttributeRule('phone', $tenant_id), new InternationalPhoneRule(false)],
            'mobile' => ['nullable', 'string', new InternationalPhoneRule(false)],
            'email' => ['sometimes', 'email', new ApiTenantAttributeRule('email', $tenant_id)],
            'address' => ['sometimes', 'string', 'max:255'],
            'trn' => ['required_if:type,==,company', 'string', 'max:255'],
            'company_person' => ['required_if:type,==,company', 'string', 'max:255'],
        ];
    }
}
