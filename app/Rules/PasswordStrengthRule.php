<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Str;

class PasswordStrengthRule implements Rule
{
    public $length = 8;

    public $lengthCheck = false;

    public $uppercaseCheck = false;

    public $numericCheck = false;

    public $specialCharacterCheck = false;

    public function __construct($length)
    {
        $this->length = $length;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->lengthCheck = Str::length($value) >= $this->length;
        $this->uppercaseCheck = Str::lower($value) !== $value;
        $this->numericCheck = (bool) preg_match('/[0-9]/', $value);
        $this->specialCharacterCheck = (bool) preg_match('/[^A-Za-z0-9]/', $value);
        return $this->lengthCheck;
//        return ($this->lengthCheck && $this->uppercaseCheck && $this->numericCheck && $this->specialCharacterCheck);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be at least ' . $this->length . ' characters and contain at least one uppercase character, one number and one special character.';
    }
}
