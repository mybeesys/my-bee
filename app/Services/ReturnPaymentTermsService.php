<?php

namespace App\Services;

use App\Models\PaymentVoucher;
use App\Models\PaymentVoucherPayment;
use App\Models\PurchasesReturns;
use App\Models\SalesReturns;
use Illuminate\Validation\ValidationException;

class ReturnPaymentTermsService
{
    public static function instance(): self
    {
        return new self();
    }

    public function recordSalesReturnCreditRefund(SalesReturns $return, array $data): void
    {
        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        $return->loadMissing(['customer.acc4', 'invoice.customer.acc4']);
        $customerAcc4 = $return->resolveCustomer()?->acc4?->code;

        if (! $customerAcc4) {
            throw ValidationException::withMessages([
                'data.credit_payment_ui.credit_payment_amount' => __('fields.invoice_payment_missing_customer_account'),
            ]);
        }

        $accountCode = (string) ($data['account_code'] ?? '120100001');
        $date = $data['date'] ?? now();
        $statement = trim((string) ($data['statement'] ?? ''));

        if ($statement === '') {
            $statement = __('fields.return_credit_refund_statement');
        }

        $voucher = PaymentVoucher::query()
            ->whereHas('payments', fn ($query) => $query
                ->where('model_type', SalesReturns::class)
                ->where('model_id', $return->id))
            ->first();

        if (! $voucher) {
            $voucher = PaymentVoucher::query()->create([
                'tenant_id' => $return->tenant_id,
                'no' => generate_payment_voucher(),
                'user_id' => auth()->id() ?? $return->user_id,
                'date' => $date,
                'for' => 'customer',
                'acc4_code' => $customerAcc4,
                'invoice_id' => $return->invoice_id,
            ]);
        }

        $payment = PaymentVoucherPayment::query()->create([
            'tenant_id' => $return->tenant_id,
            'user_id' => auth()->id() ?? $return->user_id,
            'payment_voucher_id' => $voucher->id,
            'amount' => $amount,
            'debit_acc4_code' => $customerAcc4,
            'credit_acc4_code' => $accountCode,
            'statement' => $statement,
            'date' => $date,
            'model_type' => SalesReturns::class,
            'model_id' => $return->id,
            'transaction_completed' => 0,
        ]);

        InvoicePaymentTermsService::instance()->completePaymentVoucherPaymentForReturn($payment, $return->invoice_id);
    }

    public function recordPurchaseReturnCreditRefund(PurchasesReturns $return, array $data): void
    {
        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        $return->loadMissing(['supplier.acc4', 'invoice.supplier.acc4']);
        $supplierAcc4 = $return->resolveSupplier()?->acc4?->code;

        if (! $supplierAcc4) {
            throw ValidationException::withMessages([
                'data.credit_payment_ui.credit_payment_amount' => __('fields.invoice_payment_missing_supplier_account'),
            ]);
        }

        $accountCode = (string) ($data['account_code'] ?? '120100001');
        $date = $data['date'] ?? now();
        $statement = trim((string) ($data['statement'] ?? ''));

        if ($statement === '') {
            $statement = __('fields.return_credit_refund_statement');
        }

        $voucher = PaymentVoucher::query()
            ->whereHas('payments', fn ($query) => $query
                ->where('model_type', PurchasesReturns::class)
                ->where('model_id', $return->id))
            ->first();

        if (! $voucher) {
            $voucher = PaymentVoucher::query()->create([
                'tenant_id' => $return->tenant_id,
                'no' => generate_payment_voucher(),
                'user_id' => auth()->id() ?? $return->user_id,
                'date' => $date,
                'for' => 'supplier',
                'acc4_code' => $supplierAcc4,
                'invoice_id' => $return->invoice_id,
            ]);
        }

        $payment = PaymentVoucherPayment::query()->create([
            'tenant_id' => $return->tenant_id,
            'user_id' => auth()->id() ?? $return->user_id,
            'payment_voucher_id' => $voucher->id,
            'amount' => $amount,
            'debit_acc4_code' => $supplierAcc4,
            'credit_acc4_code' => $accountCode,
            'statement' => $statement,
            'date' => $date,
            'model_type' => PurchasesReturns::class,
            'model_id' => $return->id,
            'transaction_completed' => 0,
        ]);

        InvoicePaymentTermsService::instance()->completePaymentVoucherPaymentForReturn($payment, $return->invoice_id);
    }
}
