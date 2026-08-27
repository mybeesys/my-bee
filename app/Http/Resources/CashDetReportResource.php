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

        $counterpartName = trim((string) ($counterpart?->account?->name ?? ''));

        $amountIn = (float) $this->amount_in;
        $amountOut = (float) $this->amount_out;

        return $this->filterFields([
            'id' => (int) $this->id,
            'accountCode' => (string) ($this->account_code ?? ''),
            'accountName' => (string) ($this->account?->name ?? ''),
            'opId' => $this->op_id,
            'voucherNo' => (string) ($this->operation?->no ?? ''),
            'transactionId' => $this->transaction_id,
            'invoiceId' => $this->invoice_id !== null ? (int) $this->invoice_id : null,
            'invoiceNo' => (string) ($this->invoice?->no ?? ''),
            'date' => optional($this->date)->format('Y-m-d') ?? '',
            'dateFormatted' => optional($this->date)->format('Y-m-d h:i A') ?? '',
            'createdAt' => optional($this->created_at)->format('Y-m-d') ?? '',
            'statement' => format_account_statement_text($this->statement),
            'amountIn' => number_format($amountIn, currency_decimals(), '.', ''),
            'amountOut' => number_format($amountOut, currency_decimals(), '.', ''),
            'debit' => $amountIn,
            'credit' => $amountOut,
            // Flutter AccountStatementModel expects non-nullable String for inFrom/outTo.
            'inFrom' => $amountIn > 0 ? $counterpartName : '',
            'outTo' => $amountOut > 0 ? $counterpartName : '',
            'balance' => number_format((float) $this->balance_post_transaction, currency_decimals(), '.', ''),
            'balanceNumeric' => (float) $this->balance_post_transaction,
            'currency' => (string) ($this->currency?->iso_code ?? main_currency_iso_code() ?? 'SAR'),
        ]);
    }
}
