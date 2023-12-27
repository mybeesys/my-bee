<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueTenantItemRule implements ValidationRule
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
        //TextInput('barcode')->rules([new UniqueTenantItemRule(Product::class, 'barcode')]);
        //for example
        //tenant one cant have duplicate barcode (bc1)
        //but another tenant can have barcode (bc1)

        $exists = app()->make($this->item_class)
            ->where($this->item_attribute, $value)
            ->where('tenant_id', filament()->getTenant()->id)
            ->where('id', '!=', $this->ignore_id)
            ->first();

        if ($exists) {
            $fail('validation.unique')->translate();
        }
    }

}
