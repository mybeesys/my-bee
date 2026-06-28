<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReceiptVoucherAllocationService
{
    public static function instance(): self
    {
        return new self();
    }

    public function unpaidSalesInvoicesForCustomer(int $customerId): Collection
    {
        return Invoice::query()
            ->sales()
            ->where('for', 'customer')
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'cancelled')
            ->with(['items', 'salesPayments', 'additionalCosts', 'services', 'order'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->filter(fn (Invoice $invoice) => (float) $invoice->total_unpaid > 0)
            ->values();
    }

    public function unpaidSalesInvoicesForAcc4Code(int $acc4Code): Collection
    {
        $customerId = Customer::query()
            ->whereRelation('acc4', 'code', $acc4Code)
            ->value('id');

        if (! $customerId) {
            return collect();
        }

        return $this->unpaidSalesInvoicesForCustomer((int) $customerId);
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @param  array<int>  $selectedInvoiceIds
     * @return array<int, array{invoice_id: int, amount: float, invoice: Invoice}>
     */
    public function allocate(float $amount, Collection $invoices, string $mode, array $selectedInvoiceIds = []): array
    {
        $amount = round($amount, currency_decimals());

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'data.paid_amount' => __('validation.min.numeric', [
                    'attribute' => __('fields.paid_amount'),
                    'min' => 0.01,
                ]),
            ]);
        }

        $pool = $invoices
            ->filter(fn (Invoice $invoice) => (float) $invoice->total_unpaid > 0)
            ->values();

        if ($mode === 'selected') {
            $selectedInvoiceIds = array_values(array_filter(array_map('intval', $selectedInvoiceIds)));

            if ($selectedInvoiceIds === []) {
                throw ValidationException::withMessages([
                    'data.customer_invoices' => __('fields.receipt_voucher_select_at_least_one_invoice'),
                ]);
            }

            $pool = $pool->filter(fn (Invoice $invoice) => in_array($invoice->id, $selectedInvoiceIds, true))->values();
        }

        if ($pool->isEmpty()) {
            throw ValidationException::withMessages([
                'data.customer_invoices' => __('fields.receipt_voucher_no_unpaid_invoices'),
            ]);
        }

        $maxAllocatable = round((float) $pool->sum(fn (Invoice $invoice) => (float) $invoice->total_unpaid), currency_decimals());

        if ($amount > $maxAllocatable) {
            throw ValidationException::withMessages([
                'data.paid_amount' => __('fields.receipt_voucher_amount_exceeds_allocatable'),
            ]);
        }

        $remaining = $amount;
        $allocations = [];

        foreach ($pool as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $unpaid = round((float) $invoice->total_unpaid, currency_decimals());
            $allocated = round(min($remaining, $unpaid), currency_decimals());

            if ($allocated <= 0) {
                continue;
            }

            $allocations[] = [
                'invoice_id' => $invoice->id,
                'amount' => $allocated,
                'invoice' => $invoice,
            ];

            $remaining = round($remaining - $allocated, currency_decimals());
        }

        if ($allocations === []) {
            throw ValidationException::withMessages([
                'data.paid_amount' => __('fields.receipt_voucher_no_unpaid_invoices'),
            ]);
        }

        return $allocations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildInvoiceLineStates(Collection $invoices, string $mode, array $selectedInvoiceIds, float $paidAmount): array
    {
        $selectedInvoiceIds = array_values(array_filter(array_map('intval', $selectedInvoiceIds)));
        $lines = [];

        foreach ($invoices as $invoice) {
            $lines[] = [
                'invoice_id' => $invoice->id,
                'no' => $invoice->no,
                'date' => $invoice->date?->format('Y-m-d') ?? $invoice->created_at?->format('Y-m-d'),
                'invoice_total' => format_amount($invoice->getItemsCost(true, true, true)),
                'remaining' => format_amount(max(0, (float) $invoice->total_unpaid)),
                'remaining_raw' => round((float) $invoice->total_unpaid, currency_decimals()),
                'selected' => $mode === 'fifo' ? true : in_array($invoice->id, $selectedInvoiceIds, true),
                'allocated' => format_amount(0),
                'allocated_raw' => 0,
            ];
        }

        if ($paidAmount <= 0) {
            return $lines;
        }

        try {
            $allocations = $this->allocate(
                $paidAmount,
                $invoices,
                $mode,
                $mode === 'selected' ? $selectedInvoiceIds : [],
            );

            $allocatedByInvoice = collect($allocations)->keyBy('invoice_id');

            foreach ($lines as $index => $line) {
                $allocation = $allocatedByInvoice->get($line['invoice_id']);

                if ($allocation) {
                    $lines[$index]['allocated'] = format_amount($allocation['amount']);
                    $lines[$index]['allocated_raw'] = $allocation['amount'];
                }
            }
        } catch (ValidationException) {
            // Preview only — validation runs again on save.
        }

        return $lines;
    }
}
