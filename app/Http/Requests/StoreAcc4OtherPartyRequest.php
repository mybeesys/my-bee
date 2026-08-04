<?php

namespace App\Http\Requests;

use App\Rules\UniqueAcc4OtherPartyNameRule;

class StoreAcc4OtherPartyRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new UniqueAcc4OtherPartyNameRule()],
        ];
    }
}
