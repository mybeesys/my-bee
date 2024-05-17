<?php

namespace App\Http\Requests;

class DeleteServiceForSalesInvoiceRequest extends BaseRequest
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
            'service_id' => ['required', 'exists:services,id'],
        ];
    }
}
