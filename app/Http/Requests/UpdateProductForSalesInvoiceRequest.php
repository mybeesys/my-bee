<?php

namespace App\Http\Requests;

class UpdateProductForSalesInvoiceRequest extends BaseRequest
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
            'sale_invoice_uid' => ['required', 'exists:invoices,uid'],
            'item_id' => ['required', 'exists:invoice_items,id'],
            'qty' => ['required', 'numeric', 'min:1', "max:".PHP_INT_MAX],
            'unit_cost' => ['required', 'numeric', 'min:1', "max:".PHP_INT_MAX],
            'discount' => ['required', 'numeric', 'min:1', "max:".PHP_INT_MAX],
            'tax_profile_id' => ['nullable', 'exists:tax_profiles,id'],
            'extras' => ['sometimes', 'array'],
            'extras.*' => ['required', 'integer'],
        ];
    }
}
