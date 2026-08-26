<?php

namespace App\Http\Resources;

use App\Models\CashDet;

class CashDetReportResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $counterpart = CashDet::query()
            ->with('account')
            ->where('op_id', $this->op_id)
            ->where('account_code', '!=', $this->account_code)
            ->first();

        $counterpartName = $counterpart?->account?->name;

        return $this->filterFields([
            'id' => $this->id,
            'accountCode' => (string) $this->account_code,
            'accountName' => $this->account?->name,
            'opId' => $this->op_id,
            'voucherNo' => $this->operation?->no,
            'transactionId' => $this->transaction_id,
            'invoiceId' => $this->invoice_id,
            'invoiceNo' => $this->invoice?->no,
            'date' => optional($this->date)->format('Y-m-d'),
            'dateFormatted' => optional($this->date)->format('Y-m-d h:i A'),
            'createdAt' => optional($this->created_at)->format('Y-m-d'),
            'statement' => format_account_statement_text($this->statement),
            'amountIn' => number_format((float) $this->amount_in, currency_decimals(), '.', ''),
            'amountOut' => number_format((float) $this->amount_out, currency_decimals(), '.', ''),
            'debit' => (float) $this->amount_in,
            'credit' => (float) $this->amount_out,
            'inFrom' => ((float) $this->amount_in > 0) ? $counterpartName : null,
            'outTo' => ((float) $this->amount_out > 0) ? $counterpartName : null,
            'balance' => number_format((float) $this->balance_post_transaction, currency_decimals(), '.', ''),
            'balanceNumeric' => (float) $this->balance_post_transaction,
            'currency' => $this->currency?->iso_code ?? main_currency_iso_code(),
        ]);
    }
}
