<?php

namespace App\Http\Requests;

use Carbon\Carbon;

class ListReceiptVoucherRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['date', 'from_date', 'to_date', 'created_from', 'created_until'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $this->merge([
                    $field => Carbon::createFromFormat('Y-m-d', $value)->format('d-m-Y'),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'for' => ['sometimes'],
            'for.*' => ['in:customer,other_entity'],
            'invoice_id' => ['sometimes'],
            'invoice_ids' => ['sometimes', 'array'],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
            'acc4_code' => ['sometimes'],
            'acc4_codes' => ['sometimes', 'array'],
            'acc4_codes.*' => ['string', 'exists:acc4,code'],
            'date' => ['sometimes', 'date', 'date_format:Y-m-d,d-m-Y'],
            'from_date' => ['sometimes', 'date', 'date_format:Y-m-d,d-m-Y'],
            'to_date' => ['sometimes', 'date', 'date_format:Y-m-d,d-m-Y', 'after_or_equal:from_date'],
            'created_from' => ['sometimes', 'date', 'date_format:Y-m-d,d-m-Y'],
            'created_until' => ['sometimes', 'date', 'date_format:Y-m-d,d-m-Y', 'after_or_equal:created_from'],
            'sort' => ['sometimes', 'in:latest,oldest'],
            'paginate' => ['sometimes'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'include_summaries' => ['sometimes'],
        ];
    }
}
