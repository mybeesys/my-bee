<?php

namespace App\Http\Requests;

use Carbon\Carbon;

class PreviewReceiptVoucherAllocationRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'acc4_code' => ['required', 'exists:acc4,code'],
            'allocation_mode' => ['sometimes', 'in:fifo,selected'],
            'selected_invoice_ids' => ['sometimes', 'array'],
            'selected_invoice_ids.*' => ['integer', 'exists:invoices,id'],
            'preselected_invoice_id' => ['sometimes', 'integer', 'exists:invoices,id'],
            'paid_amount' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
