<?php

namespace App\Http\Requests;

class ApplyOverallDiscountForPurchaseInvoiceRequest extends BaseRequest
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
            'discount_method' => ['required', 'in:amount,percent'],
            'amount' => ['required_if:discount_method,==,amount', 'min:1', "max:".PHP_INT_MAX],
            'percent' => ['required_if:discount_method,==,percent', 'min:1', "max:99"],
        ];
    }
}
