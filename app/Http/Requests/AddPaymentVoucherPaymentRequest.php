<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\File;

class AddPaymentVoucherPaymentRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_voucher_id' => ['required', 'exists:payment_vouchers,id'],
            'acc4_code' => ['required', 'exists:acc4,code'],
            'date' => ['required', 'date', 'date_format:d-m-Y'],
            'amount' => ['required', 'numeric', 'min:1', "max:".PHP_INT_MAX],
            'statement' => ['required', 'string', 'max:255'],
            'attachments' => ['sometimes','array'],
            'attachments.*' => ['required', 'file', File::types(['png', 'PNG', 'jpg', 'JPG', 'jpeg', 'JPEG'])->max(1024)],
        ];
    }
}
