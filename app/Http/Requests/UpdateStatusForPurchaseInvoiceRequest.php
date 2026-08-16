<?php

namespace App\Http\Requests;

class UpdateStatusForPurchaseInvoiceRequest extends BaseRequest
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
            'purchase_invoice_uid' => ['required', 'exists:invoices,uid'],
            'status' => ['required', 'in:confirmed,cancelled'],
            'payment_terms' => ['sometimes', 'in:cash,credit'],
            'credit_payment' => ['sometimes', 'nullable', 'array'],
            'credit_payment.account_code' => ['sometimes', 'nullable', 'exists:acc4,code'],
            'credit_payment.amount' => ['sometimes', 'numeric', 'min:0', 'max:' . PHP_INT_MAX],
            'credit_payment.date' => ['sometimes', 'date'],
            'credit_payment.statement' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
