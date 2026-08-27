<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierAccountStatementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        if ($this->resource === null) {
            return [
                'hasAccount' => false,
                'message' => __('fields.supplier_account_statement_no_account'),
            ];
        }

        $statement = $this->resource;

        return [
            'hasAccount' => true,
            'supplierName' => $statement['supplier_name'],
            'accountCode' => (string) $statement['account_code'],
            'from' => $statement['from'],
            'to' => $statement['to'],
            'currency' => $statement['currency'],
            'openingBalance' => (float) $statement['opening_balance'],
            'totalDebit' => (float) $statement['total_debit'],
            'totalCredit' => (float) $statement['total_credit'],
            'closingBalance' => (float) $statement['closing_balance'],
            'currentBalance' => (float) $statement['current_balance'],
            'supplyOrdersCount' => (int) $statement['supply_orders_count'],
            'purchaseInvoicesCount' => (int) $statement['purchase_invoices_count'],
            'unpaidTotal' => (float) $statement['unpaid_total'],
            'lines' => collect($statement['lines'])->map(function (array $line) {
                $date = $line['date'] ?? null;

                if ($date instanceof Carbon) {
                    $date = $date->format('Y-m-d');
                } elseif ($date) {
                    $date = Carbon::parse($date)->format('Y-m-d');
                }

                return [
                    'id' => $line['id'],
                    'date' => $date ?? '',
                    'voucherNo' => (string) ($line['voucher_no'] ?? ''),
                    'statement' => format_account_statement_text($line['statement'] ?? null),
                    'debit' => (float) $line['debit'],
                    'credit' => (float) $line['credit'],
                    'balance' => (float) $line['balance'],
                    'invoiceId' => $line['invoice_id'],
                    'invoiceNo' => (string) ($line['invoice_no'] ?? ''),
                ];
            })->values(),
        ];
    }
}
