<?php

namespace App\Http\Requests;

class StoreAcc4Request extends BaseRequest
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
            'acc3_code' => ['required', 'exists:acc3,code'],
            'code' => ['required', 'string', 'max:20', 'unique:acc4,code'],
            'name' => ['required', 'string'],
        ];
    }
}
