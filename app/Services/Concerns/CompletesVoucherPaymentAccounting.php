<?php

namespace App\Services\Concerns;

use App\Models\PaymentVoucherPayment;
use App\Models\ReceiptVoucherPayment;
use App\Services\AccountingService;

trait CompletesVoucherPaymentAccounting
{
    protected function completeReceiptVoucherPaymentAccounting(ReceiptVoucherPayment $payment, ?int $invoiceId = null): void
    {
        if ($payment->transaction_completed) {
            return;
        }

        $payment->loadMissing('debitAccount');

        $op = (int) ($payment->debitAccount?->acc3_code ?? 0) === 1227
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
            )->make($payment->credit_acc4_code, $payment->debit_acc4_code)
            ->finish();

        $payment->update(['transaction_completed' => 1]);
    }

    protected function completePaymentVoucherPaymentAccounting(PaymentVoucherPayment $payment, ?int $invoiceId = null): void
    {
        if ($payment->transaction_completed) {
            return;
        }

        $payment->loadMissing('creditAccount');

        $op = (int) ($payment->creditAccount?->acc3_code ?? 0) === 1227
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
            )->make($payment->credit_acc4_code, $payment->debit_acc4_code)
            ->finish();

        $payment->update(['transaction_completed' => 1]);
    }
}
