<?php

namespace App\Http\Requests;

use Carbon\Carbon;

class RecordPurchaseCreditPaymentRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('date')) {
            $this->merge(['date' => $this->normalizeDate($this->input('date'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_code' => ['sometimes', 'nullable', 'exists:acc4,code'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . PHP_INT_MAX],
            'date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'statement' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    protected function normalizeDate(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return $value;
    }
}
