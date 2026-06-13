<?php

namespace App\Http\Requests;

class AddAdditionalCostForPurchaseInvoiceRequest extends BaseRequest
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
            'additional_cost_type_id' => ['required', 'exists:additional_cost_types,id'],
            'cost' => ['required', 'numeric', 'min:1', "max:".PHP_INT_MAX],
            'statement' => ['required', 'string', 'max:255'],
        ];
    }
}
