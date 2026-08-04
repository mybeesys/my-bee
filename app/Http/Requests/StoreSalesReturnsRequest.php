<?php

namespace App\Http\Requests;

class StoreSalesReturnsRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $returnMode = $this->input('returnMode', $this->input('return_mode', 'invoice'));

        $rules = [
            'returnMode' => ['sometimes', 'in:invoice,customer'],
            'return_mode' => ['sometimes', 'in:invoice,customer'],
            'notes' => ['nullable', 'string'],
            'paymentTerms' => ['sometimes', 'in:cash,credit'],
            'payment_terms' => ['sometimes', 'in:cash,credit'],
            'pricesIncludesTaxes' => ['sometimes', 'boolean'],
            'prices_includes_taxes' => ['sometimes', 'boolean'],
            'creditPayment' => ['sometimes', 'array'],
            'creditPayment.accountCode' => ['sometimes', 'string'],
            'creditPayment.amount' => ['sometimes', 'numeric', 'min:0'],
            'creditPayment.date' => ['sometimes', 'date'],
            'creditPayment.statement' => ['sometimes', 'string'],
        ];

        if ($returnMode === 'customer') {
            $rules['customerId'] = ['required_without:customer_id', 'integer', 'exists:customers,id'];
            $rules['customer_id'] = ['required_without:customerId', 'integer', 'exists:customers,id'];
            $rules['details'] = ['required', 'array', 'min:1'];
            $rules['details.*.productLineKey'] = ['required_without:details.*.product_line_key', 'string'];
            $rules['details.*.product_line_key'] = ['required_without:details.*.productLineKey', 'string'];
            $rules['details.*.qty'] = ['required', 'numeric', 'min:0.0001'];
        } else {
            $rules['invoiceNo'] = ['required_without_all:invoice_no,invoiceId,invoice_id', 'string', 'exists:invoices,no'];
            $rules['invoice_no'] = ['required_without_all:invoiceNo,invoiceId,invoice_id', 'string', 'exists:invoices,no'];
            $rules['invoiceId'] = ['sometimes', 'integer', 'exists:invoices,id'];
            $rules['invoice_id'] = ['sometimes', 'integer', 'exists:invoices,id'];

            $rules['details'] = ['sometimes', 'array', 'min:1'];
            $rules['details.*.invoiceItemId'] = ['required_without:details.*.id', 'integer', 'exists:invoice_items,id'];
            $rules['details.*.id'] = ['required_without:details.*.invoiceItemId', 'integer', 'exists:invoice_items,id'];
            $rules['details.*.qty'] = ['required_with:details', 'numeric', 'min:0.0001'];

            // Legacy mobile payload
            $rules['items'] = ['required_without:details', 'array', 'min:1'];
            $rules['items.*.id'] = ['required_with:items', 'integer', 'exists:invoice_items,id'];
            $rules['items.*.qty'] = ['required_with:items', 'numeric', 'min:0.0001'];
        }

        return $rules;
    }
}
