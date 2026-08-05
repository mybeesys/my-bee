<?php

namespace App\Rules;

use App\Models\User;
use App\Support\StorePhone;
use Illuminate\Contracts\Validation\Rule;

class PhoneRule implements Rule
{
    protected $check_phone_existence;

    protected $international;

    protected $already_exists = false;

    public function __construct($check_phone_existence = true, $international = true)
    {
        $this->check_phone_existence = $check_phone_existence;
        $this->international = $international;
    }

    public function passes($attribute, $value)
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        $value = (string) $value;

        $valid = $this->international
            ? StorePhone::isValidForApi($value)
            : StorePhone::isSaudiMobile($value);

        if (! $valid) {
            return false;
        }

        if (! $this->check_phone_existence) {
            return true;
        }

        $variants = StorePhone::variants($value);
        $exists = User::query()->whereIn('phone', $variants)->exists();

        if ($exists) {
            $this->already_exists = true;

            return false;
        }

        return true;
    }

    public function getErrorMessage()
    {
        return $this->message();
    }

    public function message()
    {
        if ($this->check_phone_existence and $this->already_exists) {
            return __('messages.phone_unique');
        }

        return __('messages.phone_invalid');
    }
}
