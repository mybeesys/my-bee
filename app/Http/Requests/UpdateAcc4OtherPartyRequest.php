<?php

namespace App\Http\Requests;

use App\Rules\UniqueAcc4OtherPartyNameRule;

class UpdateAcc4OtherPartyRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $code = $this->route('code');

        return [
            'name' => ['required', 'string', 'max:255', new UniqueAcc4OtherPartyNameRule($code)],
        ];
    }
}
