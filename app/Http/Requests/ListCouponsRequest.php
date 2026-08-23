<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use App\Rules\ApiUniqueTenantItemRule;
use Illuminate\Validation\Rule;

class ListCouponsRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'span' => ['sometimes', Rule::in(['one-time', 'specified-time', 'unlimited-time'])],
            'type' => ['sometimes', Rule::in([Coupon::$TYPE_FIXED, Coupon::$TYPE_PERCENT])],
            'include_summaries' => ['sometimes', 'boolean'],
            'paginate' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::in(['latest', 'oldest'])],
        ];
    }
}
