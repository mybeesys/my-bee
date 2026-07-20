<?php

namespace App\Http\Requests;

use App\Rules\StorePhoneRule;
use App\Support\StorePhone;

class StoreTrackOrdersRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => StorePhone::normalize($this->input('phone')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new StorePhoneRule()],
        ];
    }
}
