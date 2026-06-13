<?php

namespace App\Rules;

use App\Models\Client;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueClientAttributeRule implements ValidationRule
{

    protected $clientAttribute, $checkUsersTableForAttribute, $ignore_client_id, $ignore_user_id;

    public function __construct($clientAttribute = null, $checkUsersTableForAttribute = null, $ignore_client_id = null, $ignore_user_id = null)
    {
        $this->clientAttribute = $clientAttribute;
        $this->checkUsersTableForAttribute = $checkUsersTableForAttribute;
        $this->ignore_client_id = $ignore_client_id;
        $this->ignore_user_id = $ignore_user_id;
    }

    /**
     * Run the validation rule.
     *
     * @param \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->clientAttribute == "phone" or $this->clientAttribute == "mobile")
            $value = str($value)->remove('+')->value();

        if ($this->clientAttribute) {
            $clientAttributeExists = Client::where($this->clientAttribute, $value)
                ->where('id', '!=', $this->ignore_client_id)
                ->first();

            if ($clientAttributeExists) {
                $fail('validation.unique')->translate();
            }
        }

        if ($this->checkUsersTableForAttribute) {
            $userAttributeExists = User::where($this->checkUsersTableForAttribute, $value)
                ->where('id', '!=', $this->ignore_user_id)
                ->first();

            if ($userAttributeExists) {
                $fail('validation.unique')->translate();
            }
        }

    }
}
