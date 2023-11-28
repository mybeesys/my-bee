<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ApiUniqueTenantItemRule implements ValidationRule
{
    protected $item_class, $item_attribute, $ignore_id;

    public function __construct($item_class, $item_attribute, $ignore_id = null)
    {
        $this->item_class = $item_class;
        $this->item_attribute = $item_attribute;
        $this->ignore_id = $ignore_id;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        //for example
        //tenant one cant have duplicate barcode (bc1)
        //but another tenant can have barcode (bc1)

        $exists = app()->make($this->item_class)
            ->where($this->item_attribute, $value)
            ->where('tenant_id', request()->header('Tenant-Id'))
            ->where('id', '!=', $this->ignore_id)
            ->first();

        if ($exists) {
            $fail('validation.unique')->translate();
        }
    }

}
