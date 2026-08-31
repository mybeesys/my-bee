<?php

namespace App\Services;

use App\Models\Acc4;
use App\Models\Invoice;
use App\Models\PaymentVoucher;
use App\Models\PaymentVoucherPayment;
use App\Models\Supplier;
use App\Services\Concerns\CompletesVoucherPaymentAccounting;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentVoucherService
{
    use CompletesVoucherPaymentAccounting;

    public static function instance(): self
    {
        return new self();
    }

    /**
     * @return array<int, string>
     */
    public static function eagerLoads(): array
    {
        return [
            'invoice.supplier',
            'invoice.purchasePayments',
            'payments.media',
            'payments.debitAccount',
            'payments.creditAccount',
            'user',
            'acc4',
        ];
    }

    public function isSupplierAllocationPayload(array $data): bool
    {
        return ($data['for'] ?? '') === 'supplier' && array_key_exists('paid_amount', $data);
    }

    public function normalizePaidAmount(mixed $value): float
    {
        return ReceiptVoucherService::instance()->normalizePaidAmount($value);
    }

    public function parseDate(mixed $value): Carbon
    {
        return Carbon::parse($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function prefill(?int $invoiceId = null): array
    {
        $payload = [
            'for' => 'supplier',
            'allocationMode' => 'selected',
            'acc4Code' => null,
            'creditAcc4Code' => Acc4::defaultCollectionAccountCode(),
            'paidAmount' => null,
            'preselectedInvoiceId' => null,
            'supplierInvoices' => [],
        ];

        if ($invoiceId) {
            $invoice = Invoice::with('supplier.acc4')->find($invoiceId);

            if ($invoice) {
                $payload['acc4Code'] = (string) ($invoice->supplier?->acc4?->code ?? '');
                $payload['preselectedInvoiceId'] = $invoice->id;
                $payload['paidAmount'] = round(max(0, (float) $invoice->total_unpaid), currency_decimals());
            }
        }

        if ($payload['acc4Code']) {
            $payload['supplierInvoices'] = $this->allocationPreview(
                (string) $payload['acc4Code'],
                'selected',
                $payload['preselectedInvoiceId'] ? [(int) $payload['preselectedInvoiceId']] : [],
                (float) ($payload['paidAmount'] ?? 0),
            );
        }

        return $payload;
    }

    /**
     * @param  array<int>  $selectedInvoiceIds
     * @return array<int, array<string, mixed>>
     */
    public function allocationPreview(
        string $acc4Code,
        string $mode,
        array $selectedInvoiceIds = [],
        float $paidAmount = 0,
    ): array {
        $invoices = PaymentVoucherAllocationService::instance()
            ->unpaidPurchaseInvoicesForAcc4Code((int) $acc4Code);

        return PaymentVoucherAllocationService::instance()->buildInvoiceLineStates(
            $invoices,
            $mode,
            $selectedInvoiceIds,
            $paidAmount,
        );
    }

    public function create(array $data, int $tenantId, int $userId): PaymentVoucher
    {
        if ($this->isSupplierAllocationPayload($data)) {
            return $this->createSupplierAllocation($data, $tenantId, $userId);
        }

        return $this->createLegacy($data, $tenantId, $userId);
    }

    public function createSupplierAllocation(array $data, int $tenantId, int $userId): PaymentVoucher
    {
        $this->assertAcc4Scope('supplier', (string) $data['acc4_code']);

        return DB::transaction(function () use ($data) {
            $creditAcc4Code = (string) ($data['credit_acc4_code'] ?? Acc4::defaultCollectionAccountCode());

            if (! Acc4::isCollectionAccountCode($creditAcc4Code)) {
                throw ValidationException::withMessages([
                    'credit_acc4_code' => __('validation.exists', ['attribute' => 'credit_acc4_code']),
                ]);
            }
            $acc4Code = (string) $data['acc4_code'];
            $paidAmount = $this->normalizePaidAmount($data['paid_amount'] ?? 0);
            $mode = $data['allocation_mode'] ?? 'fifo';
            $description = trim((string) ($data['description'] ?? ''));
            $date = $this->parseDate($data['date'] ?? now());

            $invoices = PaymentVoucherAllocationService::instance()
                ->unpaidPurchaseInvoicesForAcc4Code((int) $acc4Code);

            $selectedIds = array_map('intval', $data['selected_invoice_ids'] ?? []);

            if ($mode === 'selected' && $selectedIds === [] && filled($data['preselected_invoice_id'] ?? null)) {
                $selectedIds = [(int) $data['preselected_invoice_id']];
            }

            $allocations = PaymentVoucherAllocationService::instance()->allocate(
                $paidAmount,
                $invoices,
                $mode,
                $selectedIds,
            );

            $voucher = InvoicePaymentTermsService::instance()->recordAllocatedSupplierPayment(
                $creditAcc4Code,
                $date,
                $description,
                $allocations,
            );

            return $voucher->fresh()->load(self::eagerLoads());
        });
    }

    public function createLegacy(array $data, int $tenantId, int $userId): PaymentVoucher
    {
        $this->assertAcc4Scope($data['for'], (string) $data['acc4_code']);

        return DB::transaction(function () use ($data, $tenantId, $userId) {
            $invoice = Invoice::with(['items', 'purchasePayments', 'additionalCosts', 'services'])
                ->find($data['invoice_id'] ?? null);

            if ($invoice && PaymentVoucher::findForInvoice((int) $invoice->id)) {
                throw ValidationException::withMessages([
                    'invoice_id' => __('fields.voucher_already_exists_for_this_invoice'),
                ]);
            }

            $payments = $data['payments'] ?? [];

            if ($invoice && collect($payments)->sum('amount') > $invoice->getItemsCost(true, true, true)) {
                throw ValidationException::withMessages([
                    'payments' => __('fields.payments_are_bigger_than_invoice_amount'),
                ]);
            }

            $voucher = PaymentVoucher::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'no' => generate_payment_voucher(),
                'for' => $data['for'],
                'invoice_id' => $invoice?->id,
                'acc4_code' => $data['acc4_code'],
                'date' => $this->parseDate($data['date'])->format('Y-m-d'),
            ]);

            foreach ($payments as $payment) {
                $line = $this->createLegacyPaymentLine($voucher, $invoice, $payment, $tenantId, $userId);
                $this->completePaymentVoucherPaymentAccounting($line, $invoice?->id);
            }

            return $voucher->fresh()->load(self::eagerLoads());
        });
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    public function addPayment(
        PaymentVoucher $voucher,
        array $payment,
        int $tenantId,
        int $userId,
        ?array $attachments = null,
    ): PaymentVoucher {
        return DB::transaction(function () use ($voucher, $payment, $tenantId, $userId, $attachments) {
            $voucher->loadMissing(['payments', 'invoice']);

            if ($voucher->invoice
                && ($voucher->payments->sum('amount') + (float) $payment['amount']) > $voucher->invoice->getItemsCost(true, true, true)) {
                throw ValidationException::withMessages([
                    'amount' => __('fields.payments_are_bigger_than_invoice_amount'),
                ]);
            }

            $line = PaymentVoucherPayment::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'payment_voucher_id' => $voucher->id,
                'model_type' => $voucher->invoice ? Invoice::class : null,
                'model_id' => $voucher->invoice_id,
                'credit_acc4_code' => $payment['acc4_code'],
                'debit_acc4_code' => $voucher->acc4_code,
                'amount' => $payment['amount'],
                'date' => $this->parseDate($payment['date'])->format('Y-m-d'),
                'statement' => $payment['statement'],
            ]);

            if ($attachments) {
                foreach ($attachments as $file) {
                    if ($file instanceof UploadedFile) {
                        $line->addMedia($file)->toMediaCollection('attachments');
                    }
                }
            }

            $this->completePaymentVoucherPaymentAccounting($line, $voucher->invoice_id);

            return $voucher->fresh()->load(self::eagerLoads());
        });
    }

    public function assertAcc4Scope(string $for, string $acc4Code): void
    {
        $allowed = match ($for) {
            'supplier' => Supplier::query()->whereRelation('acc4', 'code', $acc4Code)->exists(),
            'other_entity' => Acc4::isOtherPartyAccountCode($acc4Code),
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages([
                'acc4_code' => __('validation.exists', ['attribute' => 'acc4_code']),
            ]);
        }
    }

    /**
     * @param  Collection<int, PaymentVoucher>  $vouchers
     * @return array<string, mixed>
     */
    public function listSummaries(Collection $vouchers): array
    {
        return [
            'paidAmount' => round((float) $vouchers->sum(fn (PaymentVoucher $voucher) => (float) $voucher->payments->sum('amount')), currency_decimals()),
            'currency' => main_currency_iso_code(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    protected function createLegacyPaymentLine(
        PaymentVoucher $voucher,
        ?Invoice $invoice,
        array $payment,
        int $tenantId,
        int $userId,
    ): PaymentVoucherPayment {
        $line = $voucher->payments()->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'payment_voucher_id' => $voucher->id,
            'model_type' => $invoice ? Invoice::class : null,
            'model_id' => $invoice?->id,
            'credit_acc4_code' => $payment['acc4_code'],
            'debit_acc4_code' => $voucher->acc4_code,
            'amount' => $payment['amount'],
            'date' => $this->parseDate($payment['date'])->format('Y-m-d'),
            'statement' => $payment['statement'],
        ]);

        if (! empty($payment['attachments']) && is_array($payment['attachments'])) {
            foreach ($payment['attachments'] as $file) {
                if ($file instanceof UploadedFile) {
                    $line->addMedia($file)->toMediaCollection('attachments');
                }
            }
        }

        return $line;
    }
}
