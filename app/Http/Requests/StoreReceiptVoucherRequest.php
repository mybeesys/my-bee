<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreReceiptVoucherRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('date')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->input('date'))) {
            $this->merge([
                'date' => Carbon::createFromFormat('Y-m-d', $this->input('date'))->format('d-m-Y'),
            ]);
        }
    }

    public function isCustomerAllocation(): bool
    {
        return $this->input('for') === 'customer' && $this->has('paid_amount');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allocation = $this->isCustomerAllocation();

        return [
            'no' => ['sometimes', 'nullable', 'string'],
            'date' => ['required', 'date', 'date_format:Y-m-d,d-m-Y'],
            'for' => ['required', 'in:customer,other_entity'],
            'acc4_code' => ['required', 'exists:acc4,code'],
            'paid_amount' => [Rule::requiredIf($allocation), 'numeric', 'min:0.01'],
            'debit_acc4_code' => ['sometimes', 'exists:acc4,code'],
            'allocation_mode' => ['sometimes', 'in:fifo,selected'],
            'selected_invoice_ids' => ['sometimes', 'array'],
            'selected_invoice_ids.*' => ['integer', 'exists:invoices,id'],
            'preselected_invoice_id' => ['sometimes', 'integer', 'exists:invoices,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2500'],
            'invoice_id' => [Rule::requiredIf(! $allocation && $this->input('for') === 'customer'), 'nullable', 'integer', 'exists:invoices,id'],
            'payments' => [Rule::requiredIf(! $allocation), 'array', Rule::when(! $allocation, 'min:1')],
            'payments.*.acc4_code' => [Rule::requiredIf(! $allocation), 'exists:acc4,code'],
            'payments.*.date' => [Rule::requiredIf(! $allocation), 'date', 'date_format:Y-m-d,d-m-Y'],
            'payments.*.amount' => [Rule::requiredIf(! $allocation), 'numeric', 'min:0.01', 'max:' . PHP_INT_MAX],
            'payments.*.statement' => [Rule::requiredIf(! $allocation), 'string', 'max:255'],
            'payments.*.attachments' => ['sometimes', 'array'],
            'payments.*.attachments.*' => ['sometimes', 'file', File::types(['png', 'PNG', 'jpg', 'JPG', 'jpeg', 'JPEG', 'webp'])->max(2048)],
        ];
    }
}
