<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueItemAttributeRule implements ValidationRule
{

    protected $item_class, $item_attribute, $ignore_id, $withoutGlobalScopes;

    public function __construct($item_class, $item_attribute, $ignore_id = null, $withoutGlobalScopes = false)
    {
        $this->item_class = $item_class;
        $this->item_attribute = $item_attribute;
        $this->ignore_id = $ignore_id;
        $this->withoutGlobalScopes = $withoutGlobalScopes;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if($this->withoutGlobalScopes)
        {
            $exists = app()->make($this->item_class)
                ->withoutGlobalScopes()
                ->where($this->item_attribute, $value)
                ->where('id', '!=', $this->ignore_id)
                ->first();
        }else{
            $exists = app()->make($this->item_class)
                ->where($this->item_attribute, $value)
                ->where('id', '!=', $this->ignore_id)
                ->first();
        }

        if ($exists) {
            $fail('validation.unique')->translate();
        }
    }
}
