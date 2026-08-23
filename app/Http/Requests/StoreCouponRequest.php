<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use App\Rules\ApiUniqueTenantItemRule;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('valid_until')) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $this->input('valid_until'))) {
            $this->merge([
                'valid_until' => Carbon::createFromFormat('d-m-Y', $this->input('valid_until'))->format('Y-m-d'),
            ]);
        }

        if ($this->has('active')) {
            $this->merge([
                'active' => filter_var($this->input('active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $span = (string) $this->input('span');
        $type = (string) $this->input('type');

        return [
            'code' => ['required', 'string', 'max:255', new ApiUniqueTenantItemRule(Coupon::class, 'code')],
            'span' => ['required', Rule::in(['one-time', 'specified-time', 'unlimited-time'])],
            'valid_until' => [
                Rule::requiredIf($span !== 'unlimited-time'),
                'nullable',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'type' => ['required', Rule::in([Coupon::$TYPE_FIXED, Coupon::$TYPE_PERCENT])],
            'amount' => [
                Rule::requiredIf($type === Coupon::$TYPE_FIXED),
                'nullable',
                'numeric',
                'min:1',
                'max:'.PHP_INT_MAX,
            ],
            'percent' => [
                Rule::requiredIf($type === Coupon::$TYPE_PERCENT),
                'nullable',
                'integer',
                'min:1',
                'max:99',
            ],
            'active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
