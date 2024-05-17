<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\ApiTenantAttributeRule;
use App\Rules\InternationalPhoneRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('sanctum')->user()->hasRole(User::ROLE_CLIENT);
    }

    protected function failedAuthorization()
    {
        abort(403, __('messages.api.permission_denied'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:company,individual'],
            'name' => ['required', 'string', 'unique:tenants', 'max:255'],
            'phone' => ['required', new ApiTenantAttributeRule('phone'), new InternationalPhoneRule()],
            'mobile' => ['nullable', 'string', new InternationalPhoneRule()],
            'email' => ['required', 'email', new ApiTenantAttributeRule('email')],
            'address' => ['required', 'string', 'max:255'],
            'trn' => ['required_if:type,==,company', 'string', 'max:255'],
            'company_person' => ['required_if:type,==,company', 'string', 'max:255'],
        ];
    }
}
