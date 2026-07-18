<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentVoucher;
use App\Models\PaymentVoucherPayment;
use App\Models\ReceiptVoucher;
use App\Models\ReceiptVoucherPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoicePaymentTermsService
{
    public const TERM_CASH = 'cash';

    public const TERM_CREDIT = 'credit';

    public static function instance(): self
    {
        return new self();
    }

    public function isCash(Invoice $invoice): bool
    {
        return ($invoice->payment_terms ?? self::TERM_CREDIT) === self::TERM_CASH;
    }

    public function isCredit(Invoice $invoice): bool
    {
        return ! $this->isCash($invoice);
    }

    /**
     * Post a full cash payment using the same voucher + accounting flow as manual entry.
     * Safe to call only after invoice is confirmed and totals are final.
     */
    /**
     * Register a partial (or full) credit payment from the invoice payments tab.
     */
    public function recordCreditPayment(Invoice $invoice, array $data): void
    {
        if (! $this->isCredit($invoice)) {
            return;
        }

        $invoice->loadMissing(['customer.acc4', 'supplier.acc4', 'salesPayments', 'purchasePayments']);

        $amount = (float) ($data['amount'] ?? 0);
        $remaining = (float) $invoice->total_unpaid;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'data.credit_payment_amount' => __('validation.min.numeric', ['attribute' => __('fields.amount'), 'min' => 0.01]),
            ]);
        }

        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'data.credit_payment_amount' => __('fields.payments_are_bigger_than_invoice_amount'),
            ]);
        }

        $accountCode = $data['account_code'] ?? '120100001';
        $date = $data['date'] ?? now();
        $statement = $data['statement'] ?? __('fields.invoice_partial_payment_statement', ['no' => $invoice->no]);

        if ($invoice->type === 'sales') {
            $customerAcc4 = $invoice->customer?->acc4?->code;

            if (! $customerAcc4) {
                throw ValidationException::withMessages([
                    'data.credit_payment_ui.credit_payment_amount' => __('fields.invoice_payment_missing_customer_account'),
                ]);
            }

            $this->recordSalesPaymentLine($invoice, $amount, $accountCode, $date, $statement, creditAcc4: $customerAcc4);
        } elseif ($invoice->type === 'purchases') {
            $supplierAcc4 = $invoice->supplier?->acc4?->code;

            if (! $supplierAcc4) {
                throw ValidationException::withMessages([
                    'data.credit_payment_ui.credit_payment_amount' => __('fields.invoice_payment_missing_supplier_account'),
                ]);
            }

            $this->recordPurchasePaymentLine($invoice, $amount, $accountCode, $date, $statement, debitAcc4: $supplierAcc4);
        }

        $invoice->refresh();

        if ($invoice->paid && $invoice->order && $invoice->order->paid_date === null) {
            $invoice->order->update(['paid_date' => now()]);
        }
    }

    public function recordFullCashPayment(Invoice $invoice): void
    {
        if (! $this->isCash($invoice)) {
            return;
        }

        if ($invoice->paid) {
            return;
        }

        $invoice->loadMissing(['customer.acc4', 'supplier.acc4', 'salesPayments', 'purchasePayments']);

        $amount = $invoice->getItemsCost(true, true, true);

        if ($amount <= 0) {
            return;
        }

        if ($invoice->type === 'sales') {
            $this->recordSalesPaymentLine(
                $invoice,
                $amount,
                '120100001',
                now(),
                __('fields.invoice_cash_payment_statement', ['no' => $invoice->no]),
                $invoice->customer?->acc4?->code,
            );
        } elseif ($invoice->type === 'purchases') {
            $this->recordPurchasePaymentLine(
                $invoice,
                $amount,
                '120100001',
                now(),
                __('fields.invoice_cash_payment_statement', ['no' => $invoice->no]),
                $invoice->supplier?->acc4?->code,
            );
        }
    }

    protected function recordSalesPaymentLine(
        Invoice $invoice,
        float $amount,
        string $debitAcc4,
        $date,
        string $statement,
        ?string $creditAcc4 = null,
    ): void {
        $creditAcc4 ??= $invoice->customer?->acc4?->code;

        $voucher = ReceiptVoucher::query()->firstOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'tenant_id' => $invoice->tenant_id,
                'no' => generate_receipt_voucher(),
                'user_id' => auth()->id(),
                'date' => now(),
                'for' => 'customer',
                'acc4_code' => $creditAcc4,
            ],
        );

        $payment = ReceiptVoucherPayment::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'user_id' => auth()->id() ?? $invoice->user_id,
            'receipt_voucher_id' => $voucher->id,
            'amount' => $amount,
            'debit_acc4_code' => $debitAcc4,
            'credit_acc4_code' => $creditAcc4,
            'statement' => $statement,
            'date' => $date,
            'model_type' => Invoice::class,
            'model_id' => $invoice->id,
            'transaction_completed' => 0,
        ]);

        $this->completeReceiptVoucherPayment($payment, $invoice->id);
    }

    protected function recordPurchasePaymentLine(
        Invoice $invoice,
        float $amount,
        string $creditAcc4,
        $date,
        string $statement,
        ?string $debitAcc4 = null,
    ): void {
        $debitAcc4 ??= $invoice->supplier?->acc4?->code;

        $voucher = PaymentVoucher::query()->firstOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'tenant_id' => $invoice->tenant_id,
                'no' => generate_payment_voucher(),
                'user_id' => auth()->id(),
                'date' => now(),
                'for' => 'supplier',
                'acc4_code' => $debitAcc4,
            ],
        );

        $payment = PaymentVoucherPayment::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'user_id' => auth()->id() ?? $invoice->user_id,
            'payment_voucher_id' => $voucher->id,
            'amount' => $amount,
            'credit_acc4_code' => $creditAcc4,
            'debit_acc4_code' => $debitAcc4,
            'statement' => $statement,
            'date' => $date,
            'model_type' => Invoice::class,
            'model_id' => $invoice->id,
            'transaction_completed' => 0,
        ]);

        $this->completePaymentVoucherPayment($payment, $invoice->id);
    }

    protected function completeReceiptVoucherPayment(ReceiptVoucherPayment $payment, ?int $invoiceId): void
    {
        if ($payment->transaction_completed) {
            return;
        }

        $payment->loadMissing('debitAccount');

        $op = $payment->debitAccount?->acc3_code == 1227
            ? make_bank_transfer_receipt_voucher_op()
            : make_cash_receipt_voucher_op();

        (new AccountingService())
            ->setUp(
                $op->id,
                now(),
                main_currency_iso_code(),
                generate_double_entry_transaction_id(),
                $payment->amount,
                null,
                $payment->statement,
                $payment->statement,
                $invoiceId,
            )
            ->make($payment->credit_acc4_code, $payment->debit_acc4_code)
            ->finish();

        $payment->update(['transaction_completed' => 1]);
    }

    protected function completePaymentVoucherPayment(PaymentVoucherPayment $payment, ?int $invoiceId): void
    {
        if ($payment->transaction_completed) {
            return;
        }

        $payment->loadMissing('creditAccount');

        $op = $payment->creditAccount?->acc3_code == 1227
            ? make_bank_transfer_payment_voucher_op()
            : make_cash_payment_voucher_op();

        (new AccountingService())
            ->setUp(
                $op->id,
                now(),
                main_currency_iso_code(),
                generate_double_entry_transaction_id(),
                $payment->amount,
                null,
                $payment->statement,
                $payment->statement,
                $invoiceId,
            )
            ->make($payment->credit_acc4_code, $payment->debit_acc4_code)
            ->finish();

        $payment->update(['transaction_completed' => 1]);
    }

    public function completePaymentVoucherPaymentForReturn(PaymentVoucherPayment $payment, ?int $invoiceId): void
    {
        $this->completePaymentVoucherPayment($payment, $invoiceId);
    }

    /**
     * @param  array<int, array{invoice_id: int, amount: float, invoice: Invoice}>  $allocations
     */
    public function recordAllocatedCustomerReceipt(
        string $customerAcc4Code,
        string $debitAcc4Code,
        $date,
        string $baseStatement,
        array $allocations,
    ): ReceiptVoucher {
        if ($allocations === []) {
            throw ValidationException::withMessages([
                'data.paid_amount' => __('fields.receipt_voucher_no_unpaid_invoices'),
            ]);
        }

        $createdVoucher = null;

        foreach ($allocations as $allocation) {
            /** @var Invoice $invoice */
            $invoice = $allocation['invoice'];
            $amount = (float) $allocation['amount'];
            $statement = trim($baseStatement) !== ''
                ? $baseStatement
                : __('fields.receipt_voucher_invoice_settlement', ['no' => $invoice->no]);

            $this->recordCreditPayment($invoice, [
                'amount' => $amount,
                'account_code' => $debitAcc4Code,
                'date' => $date,
                'statement' => $statement,
            ]);

            $createdVoucher ??= ReceiptVoucher::findForInvoice($invoice->id);
        }

        return $createdVoucher?->fresh(['payments', 'acc4'])
            ?? throw ValidationException::withMessages([
                'data.paid_amount' => __('fields.receipt_voucher_no_unpaid_invoices'),
            ]);
    }

    /**
     * @param  array<int, array{invoice_id: int, amount: float, invoice: Invoice}>  $allocations
     */
    public function recordAllocatedSupplierPayment(
        string $creditAcc4Code,
        $date,
        string $baseStatement,
        array $allocations,
    ): PaymentVoucher {
        if ($allocations === []) {
            throw ValidationException::withMessages([
                'data.paid_amount' => __('fields.receipt_voucher_no_unpaid_invoices'),
            ]);
        }

        $createdVoucher = null;

        foreach ($allocations as $allocation) {
            /** @var Invoice $invoice */
            $invoice = $allocation['invoice'];
            $amount = (float) $allocation['amount'];
            $statement = trim($baseStatement) !== ''
                ? $baseStatement
                : __('fields.payment_voucher_invoice_settlement', ['no' => $invoice->no]);

            $this->recordCreditPayment($invoice, [
                'amount' => $amount,
                'account_code' => $creditAcc4Code,
                'date' => $date,
                'statement' => $statement,
            ]);

            $createdVoucher ??= PaymentVoucher::findForInvoice($invoice->id);
        }

        return $createdVoucher?->fresh(['payments', 'acc4'])
            ?? throw ValidationException::withMessages([
                'data.paid_amount' => __('fields.receipt_voucher_no_unpaid_invoices'),
            ]);
    }
}
