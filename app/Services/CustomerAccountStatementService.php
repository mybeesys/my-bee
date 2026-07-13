<?php

namespace App\Services;

use App\Models\CashDet;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Collection;

class CustomerAccountStatementService
{
    /**
     * @return array{
     *     customer_name: string,
     *     account_code: int|string,
     *     from: ?string,
     *     to: ?string,
     *     currency: string,
     *     opening_balance: float,
     *     total_debit: float,
     *     total_credit: float,
     *     closing_balance: float,
     *     current_balance: float,
     *     orders_count: int,
     *     invoices_count: int,
     *     unpaid_total: float,
     *     lines: Collection<int, array{
     *         id: int,
     *         date: mixed,
     *         voucher_no: mixed,
     *         statement: string,
     *         debit: float,
     *         credit: float,
     *         balance: float,
     *         invoice_id: ?int,
     *         invoice_no: mixed,
     *     }>,
     * }|null
     */
    public function build(Customer $customer, ?string $from = null, ?string $to = null): ?array
    {
        $customer->loadMissing('acc4');

        if (! $customer->acc4?->code) {
            return null;
        }

        $accountCode = $customer->acc4->code;

        $query = CashDet::query()
            ->with(['operation', 'invoice'])
            ->where('account_code', $accountCode)
            ->orderBy('date')
            ->orderBy('id');

        if ($from) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        $lines = $query->get();

        $openingBalance = 0.0;

        if ($from) {
            $prior = CashDet::query()
                ->where('account_code', $accountCode)
                ->whereDate('date', '<', $from)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first();

            $openingBalance = (float) ($prior?->balance_post_transaction ?? 0);
        }

        $currentBalance = (float) (CashDet::query()
            ->where('account_code', $accountCode)
            ->orderByDesc('id')
            ->value('balance_post_transaction') ?? 0);

        $closingBalance = $lines->isNotEmpty()
            ? (float) $lines->last()->balance_post_transaction
            : $openingBalance;

        return [
            'customer_name' => $customer->name,
            'account_code' => $accountCode,
            'from' => $from,
            'to' => $to,
            'currency' => main_currency_iso_code(),
            'opening_balance' => round($openingBalance, 2),
            'total_debit' => round((float) $lines->sum('amount_in'), 2),
            'total_credit' => round((float) $lines->sum('amount_out'), 2),
            'closing_balance' => round($closingBalance, 2),
            'current_balance' => round($currentBalance, 2),
            'orders_count' => $customer->orders()->count(),
            'invoices_count' => $customer->invoices()->where('type', 'sales')->where('status', 'confirmed')->count(),
            'unpaid_total' => round($this->unpaidSalesTotal($customer), 2),
            'lines' => $lines->map(fn (CashDet $line) => [
                'id' => $line->id,
                'date' => $line->date,
                'voucher_no' => $line->operation?->no,
                'statement' => format_account_statement_text($line->statement),
                'debit' => (float) $line->amount_in,
                'credit' => (float) $line->amount_out,
                'balance' => (float) $line->balance_post_transaction,
                'invoice_id' => $line->invoice_id,
                'invoice_no' => $line->invoice?->no,
            ]),
        ];
    }

    protected function unpaidSalesTotal(Customer $customer): float
    {
        return (float) Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('type', 'sales')
            ->where('status', 'confirmed')
            ->with('salesPayments')
            ->get()
            ->sum(fn (Invoice $invoice) => max(0, $invoice->total_unpaid));
    }
}
