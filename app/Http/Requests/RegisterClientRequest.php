<?php

namespace App\Http\Requests;

use App\Rules\InternationalPhoneRule;
use App\Rules\PasswordStrengthRule;

class RegisterClientRequest extends BaseRequest
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
            'name' => ['required', 'min:2', 'max:255'],
            'phone' => ['required', new InternationalPhoneRule()],
            'email' => ['required', 'email', 'unique:users', 'max:255'],
            'password' => ['required', 'confirmed', new PasswordStrengthRule(6), 'max:255'],
            'address' => ['nullable', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
        ];
    }
}
