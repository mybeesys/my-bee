<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PurchasesReturnsDetails;
use App\Models\SalesReturnsDetails;
use Illuminate\Support\Collection;

class IncomeStatementService
{
    /**
     * @return array{
     *     from: ?string,
     *     to: ?string,
     *     currency: string,
     *     sales_total: float,
     *     purchases_total: float,
     *     expenses_total: float,
     *     net_income: float,
     *     sales_lines: Collection,
     *     purchases_lines: Collection,
     *     expense_lines: Collection,
     *     sales_gross: float,
     *     sales_returns: float,
     *     purchases_gross: float,
     *     purchase_returns: float,
     *     sales_invoices_count: int,
     *     purchase_invoices_count: int,
     * }
     */
    public function build(?string $from = null, ?string $to = null): array
    {
        $salesInvoices = $this->confirmedInvoicesQuery(Invoice::$TYPE_SALES, $from, $to)->get();
        $purchaseInvoices = $this->confirmedInvoicesQuery(Invoice::$TYPE_PURCHASES, $from, $to)->get();

        $salesGross = $this->sumInvoiceTotals($salesInvoices);
        $salesReturns = $this->salesReturnsTotal($from, $to);
        $salesTotal = round($salesGross - $salesReturns, 2);

        $purchasesGross = $this->sumInvoiceTotals($purchaseInvoices);
        $purchaseReturns = $this->purchaseReturnsTotal($from, $to);
        $purchasesTotal = round($purchasesGross - $purchaseReturns, 2);

        $expenseLines = $this->expenseLines($from, $to);
        $expensesTotal = round((float) $expenseLines->sum('net'), 2);

        return [
            'from' => $from,
            'to' => $to,
            'currency' => main_currency_iso_code(),
            'sales_total' => $salesTotal,
            'purchases_total' => $purchasesTotal,
            'expenses_total' => $expensesTotal,
            'net_income' => round($salesTotal - $purchasesTotal - $expensesTotal, 2),
            'sales_lines' => $this->buildSalesLines($salesGross, $salesReturns, $salesInvoices->count()),
            'purchases_lines' => $this->buildPurchasesLines($purchasesGross, $purchaseReturns, $purchaseInvoices->count()),
            'expense_lines' => $expenseLines,
            'sales_gross' => $salesGross,
            'sales_returns' => $salesReturns,
            'purchases_gross' => $purchasesGross,
            'purchase_returns' => $purchaseReturns,
            'sales_invoices_count' => $salesInvoices->count(),
            'purchase_invoices_count' => $purchaseInvoices->count(),
        ];
    }

    /**
     * @return Collection<int, object{code: string, name: string, net: float}>
     */
    protected function buildSalesLines(float $gross, float $returns, int $invoiceCount): Collection
    {
        $lines = collect();

        if ($gross > 0 || $invoiceCount > 0) {
            $lines->push((object) [
                'code' => '',
                'name' => $this->lineLabel(
                    __('fields.income_statement_sales_invoices'),
                    $invoiceCount,
                ),
                'net' => round($gross, 2),
            ]);
        }

        if ($returns > 0) {
            $lines->push((object) [
                'code' => '',
                'name' => __('fields.income_statement_sales_returns'),
                'net' => round(-$returns, 2),
            ]);
        }

        return $lines;
    }

    /**
     * @return Collection<int, object{code: string, name: string, net: float}>
     */
    protected function buildPurchasesLines(float $gross, float $returns, int $invoiceCount): Collection
    {
        $lines = collect();

        if ($gross > 0 || $invoiceCount > 0) {
            $lines->push((object) [
                'code' => '',
                'name' => $this->lineLabel(
                    __('fields.income_statement_purchase_invoices'),
                    $invoiceCount,
                ),
                'net' => round($gross, 2),
            ]);
        }

        if ($returns > 0) {
            $lines->push((object) [
                'code' => '',
                'name' => __('fields.income_statement_purchase_returns'),
                'net' => round(-$returns, 2),
            ]);
        }

        return $lines;
    }

    /**
     * @return Collection<int, object{code: string, name: string, net: float}>
     */
    protected function expenseLines(?string $from, ?string $to): Collection
    {
        $query = Expense::query();

        $tenantId = $this->tenantId();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($from) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        return $query
            ->with('category')
            ->get()
            ->filter(fn (Expense $expense) => blank(data_get($expense->meta, 'invoice_additional_cost_id')))
            ->groupBy(fn (Expense $expense) => $expense->category?->name ?? __('fields.income_statement_expense_uncategorized'))
            ->map(function (Collection $group, string $categoryName) {
                return (object) [
                    'code' => '',
                    'name' => $categoryName,
                    'net' => round((float) $group->sum(fn (Expense $expense) => $this->expenseTotal($expense)), 2),
                ];
            })
            ->filter(fn (object $line) => $line->net > 0)
            ->sortBy('name')
            ->values();
    }

    protected function confirmedInvoicesQuery(string $type, ?string $from, ?string $to)
    {
        $query = Invoice::query()
            ->where('type', $type)
            ->where('status', 'confirmed')
            ->where('temp', false)
            ->with(['items', 'additionalCosts', 'services']);

        $tenantId = $this->tenantId();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($from) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        return $query;
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    protected function sumInvoiceTotals(Collection $invoices): float
    {
        return round((float) $invoices->sum(
            fn (Invoice $invoice) => (float) $invoice->getItemsCost(true, true, true)
        ), 2);
    }

    protected function salesReturnsTotal(?string $from, ?string $to): float
    {
        $query = SalesReturnsDetails::query();

        $tenantId = $this->tenantId();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return round((float) $query->sum('total'), 2);
    }

    protected function purchaseReturnsTotal(?string $from, ?string $to): float
    {
        $query = PurchasesReturnsDetails::query();

        $tenantId = $this->tenantId();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return round((float) $query->sum('total'), 2);
    }

    protected function expenseTotal(Expense $expense): float
    {
        return (float) $expense->getRawOriginal('amount') + (float) $expense->getRawOriginal('tax');
    }

    protected function lineLabel(string $label, int $count): string
    {
        if ($count <= 0) {
            return $label;
        }

        return $label . ' (' . $count . ')';
    }

    protected function tenantId(): ?int
    {
        return filament()->getTenant()?->id ?? request()->header('Tenant-Id');
    }
}
