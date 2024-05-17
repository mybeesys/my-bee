<?php

namespace App\Http\Requests;

class AddServiceForSalesInvoiceRequest extends BaseRequest
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
            'service_type_id' => ['required', 'exists:service_types,id'],
            'tax_profile_id' => ['nullable', 'exists:tax_profiles,id'],
            'price' => ['required', 'numeric', 'min:1', "max:".PHP_INT_MAX],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
