<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class MustNotBeTheSameRule implements Rule
{
    public $first_value;
    public $second_value;
    public $message;

    /**
     * Create a new rule instance.
     *
     * @param $first_value
     * @param $second_value
     * @param $message
     */
    public function __construct($first_value, $second_value, $message)
    {
        $this->first_value = $first_value;
        $this->second_value = $second_value;
        $this->message = $message;
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
        return $this->first_value != $this->second_value;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
