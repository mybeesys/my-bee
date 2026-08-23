<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\File;

class AddReceiptVoucherPaymentRequest extends BaseRequest
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
            'receipt_voucher_id' => ['required', 'exists:receipt_vouchers,id'],
            'acc4_code' => ['required', 'exists:acc4,code'],
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.PHP_INT_MAX],
            'statement' => ['required', 'string', 'max:255'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['required', 'file', File::types(['png', 'PNG', 'jpg', 'JPG', 'jpeg', 'JPEG'])->max(2048)],
        ];
    }
}
