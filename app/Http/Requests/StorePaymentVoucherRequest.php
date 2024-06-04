<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\File;

class StorePaymentVoucherRequest extends BaseRequest
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
            'no' => ['required', 'string'],
            'date' => ['required', 'date', 'date_format:d-m-Y'],
            'for' => ['required', 'in:supplier,other_entity'],
            'invoice_id' => ['required_if:for,==,supplier', 'numeric', 'exists:invoices,id'],
            'acc4_code' => ['required', 'exists:acc4,code'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.acc4_code' => ['required', 'exists:acc4,code'],
            'payments.*.date' => ['required', 'date', 'date_format:d-m-Y'],
            'payments.*.amount' => ['required', 'numeric', 'min:1', "max:".PHP_INT_MAX],
            'payments.*.statement' => ['required', 'string', 'max:255'],
            'payments.*.attachments' => ['sometimes','array'],
            'payments.*.attachments.*' => ['sometimes', 'file', File::types(['png', 'PNG', 'jpg', 'JPG', 'jpeg', 'JPEG'])->max(1024)],
        ];
    }
}
