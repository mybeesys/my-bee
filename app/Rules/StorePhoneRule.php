<?php

namespace App\Rules;

use App\Support\StorePhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StorePhoneRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! StorePhone::isValid(is_string($value) ? $value : (string) $value)) {
            $fail(__('messages.phone_invalid'));
        }
    }
}
