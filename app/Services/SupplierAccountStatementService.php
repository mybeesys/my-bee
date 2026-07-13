<?php

namespace App\Services;

use App\Models\CashDet;
use App\Models\Invoice;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class SupplierAccountStatementService
{
    /**
     * @return array{
     *     supplier_name: string,
     *     account_code: int|string,
     *     from: ?string,
     *     to: ?string,
     *     currency: string,
     *     opening_balance: float,
     *     total_debit: float,
     *     total_credit: float,
     *     closing_balance: float,
     *     current_balance: float,
     *     supply_orders_count: int,
     *     purchase_invoices_count: int,
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
    public function build(Supplier $supplier, ?string $from = null, ?string $to = null): ?array
    {
        $supplier->loadMissing('acc4');

        if (! $supplier->acc4?->code) {
            return null;
        }

        $accountCode = $supplier->acc4->code;

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
            'supplier_name' => $supplier->name,
            'account_code' => $accountCode,
            'from' => $from,
            'to' => $to,
            'currency' => main_currency_iso_code(),
            'opening_balance' => round($openingBalance, 2),
            'total_debit' => round((float) $lines->sum('amount_in'), 2),
            'total_credit' => round((float) $lines->sum('amount_out'), 2),
            'closing_balance' => round($closingBalance, 2),
            'current_balance' => round($currentBalance, 2),
            'supply_orders_count' => $supplier->supplyOrders()->count(),
            'purchase_invoices_count' => $supplier->purchaseInvoices()->where('status', 'confirmed')->count(),
            'unpaid_total' => round($this->unpaidPurchasesTotal($supplier), 2),
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

    protected function unpaidPurchasesTotal(Supplier $supplier): float
    {
        return (float) Invoice::query()
            ->where('supplier_id', $supplier->id)
            ->where('type', 'purchases')
            ->where('status', 'confirmed')
            ->where('temp', false)
            ->with('purchasePayments')
            ->get()
            ->sum(fn (Invoice $invoice) => max(0, $invoice->total_unpaid));
    }
}
