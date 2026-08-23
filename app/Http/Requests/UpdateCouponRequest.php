<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use App\Rules\ApiUniqueTenantItemRule;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends BaseRequest
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
        $id = str(request()->getRequestUri())->afterLast('/')->value();
        $existing = Coupon::query()->find($id);
        $span = (string) ($this->input('span') ?? $existing?->span ?? 'specified-time');
        $type = (string) ($this->input('type') ?? $existing?->type ?? Coupon::$TYPE_PERCENT);

        return [
            'code' => ['sometimes', 'string', 'max:255', new ApiUniqueTenantItemRule(Coupon::class, 'code', $id)],
            'span' => ['sometimes', Rule::in(['one-time', 'specified-time', 'unlimited-time'])],
            'valid_until' => [
                Rule::requiredIf($span !== 'unlimited-time'),
                'nullable',
                'date',
                'date_format:Y-m-d',
            ],
            'type' => ['sometimes', Rule::in([Coupon::$TYPE_FIXED, Coupon::$TYPE_PERCENT])],
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
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
