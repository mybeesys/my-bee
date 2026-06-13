<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PhoneRule implements Rule
{
    protected $check_phone_existence, $international, $already_exists = false;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($check_phone_existence = true, $international = true)
    {
        $this->check_phone_existence = $check_phone_existence;
        $this->international = $international;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!Str::startsWith($value, '+'))
            $value = '+' . $value;

        $rules = $this->international ? ['phone:INTERNATIONAL'] : ['phone'];

        $passes = Validator::make([$value], $rules)->passes();
        if (!$passes or !is_numeric($value))
            return false;

        $value = Str::replace('+', '', $value);

        $exists = User::firstWhere('phone', Str::replace('+', '', $value));

        if ($this->check_phone_existence and $exists) {
            $this->already_exists = true;
            return false;
        }
        return true;
    }

    public function getErrorMessage()
    {
        if ($this->check_phone_existence and $this->already_exists)
            return __('messages.phone_unique');

        return __('messages.phone_invalid');
    }
    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        if ($this->check_phone_existence and $this->already_exists)
            return __('messages.phone_unique');

        return __('messages.phone_invalid');
    }
}
