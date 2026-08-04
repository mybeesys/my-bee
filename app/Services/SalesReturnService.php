<?php

namespace App\Services;

use App\Filament\Tenant\Resources\SalesReturnsResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SalesReturns;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    public static function instance(): self
    {
        return new self();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, int $tenantId, int $userId): SalesReturns
    {
        $formData = $this->normalizePayload($payload, $tenantId, $userId);
        $returnMode = $formData['return_mode'] ?? 'invoice';

        $error = SalesReturnsResource::validateReturnDetailsForCreate($formData);

        if ($error) {
            throw ValidationException::withMessages(['details' => $error]);
        }

        if (($formData['payment_terms'] ?? 'cash') === 'credit') {
            $returnTotal = SalesReturnWorkflow::sumReturnDetailsTotals($formData['details'] ?? []);
            $refundAmount = SalesReturnWorkflow::normalizeCreditAmount(
                $formData['credit_payment_ui']['credit_payment_amount'] ?? 0
            );

            if ($refundAmount > $returnTotal) {
                throw ValidationException::withMessages([
                    'creditPayment' => __('fields.payments_are_bigger_than_invoice_amount'),
                ]);
            }
        }

        return DB::transaction(function () use ($formData, $returnMode, $tenantId, $userId) {
            $record = SalesReturns::create([
                'tenant_id' => $tenantId,
                'invoice_id' => $returnMode === 'customer' ? null : ($formData['invoice_id'] ?? null),
                'customer_id' => $returnMode === 'customer'
                    ? ($formData['customer_id'] ?? null)
                    : (Invoice::find($formData['invoice_id'] ?? null)?->customer_id),
                'notes' => $formData['notes'] ?? null,
                'payment_terms' => $formData['payment_terms'] ?? 'cash',
                'refund_acc4_code' => $formData['refund_acc4_code'] ?? null,
                'user_id' => $userId,
            ]);

            if ($returnMode === 'invoice') {
                foreach ($formData['details'] ?? [] as $detail) {
                    $record->details()->create([
                        ...SalesReturnWorkflow::normalizeDetailForSave($detail),
                        'tenant_id' => $tenantId,
                        'sales_returns_id' => $record->id,
                        'user_id' => $userId,
                    ]);
                }
            }

            $returnTotal = SalesReturnWorkflow::syncExpandedReturnDetails(
                $record,
                $formData,
                $returnMode,
                'sales'
            );

            SalesReturnWorkflow::settleSalesReturnPayment($record, $formData, $returnTotal);
            $this->processCreditRefund($record, $formData, $returnTotal);

            foreach ($record->fresh('details')->details as $detail) {
                $detail->update(['transaction_completed' => true]);
            }

            return $record->fresh(['details.invoiceItem', 'invoice', 'customer', 'user']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizePayload(array $payload, int $tenantId, int $userId): array
    {
        $returnMode = $payload['returnMode'] ?? $payload['return_mode'] ?? 'invoice';
        $pricesIncludesTaxes = (bool) ($payload['pricesIncludesTaxes'] ?? $payload['prices_includes_taxes'] ?? true);
        $paymentTerms = $payload['paymentTerms'] ?? $payload['payment_terms'] ?? 'cash';
        $notes = $payload['notes'] ?? null;

        $creditUi = $payload['creditPayment'] ?? $payload['credit_payment_ui'] ?? [];
        if (is_array($creditUi) && $creditUi !== []) {
            $creditUi = [
                'credit_payment_account' => $creditUi['accountCode'] ?? $creditUi['credit_payment_account'] ?? '120100001',
                'credit_payment_amount' => $creditUi['amount'] ?? $creditUi['credit_payment_amount'] ?? 0,
                'credit_payment_date' => $creditUi['date'] ?? $creditUi['credit_payment_date'] ?? now()->toDateString(),
                'credit_payment_statement' => $creditUi['statement'] ?? $creditUi['credit_payment_statement'] ?? '',
            ];
        }

        $detailsInput = $payload['details'] ?? null;

        if ($detailsInput === null && isset($payload['items'])) {
            $detailsInput = collect($payload['items'])->map(fn (array $item) => [
                'invoice_item_id' => $item['invoiceItemId'] ?? $item['id'] ?? null,
                'qty' => $item['qty'] ?? 0,
            ])->all();
        }

        $details = $this->buildDetails(
            (string) $returnMode,
            $detailsInput ?? [],
            $pricesIncludesTaxes,
            (int) ($payload['customerId'] ?? $payload['customer_id'] ?? 0),
        );

        $invoiceId = null;

        if ($returnMode === 'invoice') {
            $invoiceNo = $payload['invoiceNo'] ?? $payload['invoice_no'] ?? null;
            $invoiceId = $payload['invoiceId'] ?? $payload['invoice_id'] ?? null;

            if ($invoiceNo) {
                $invoiceId = Invoice::query()
                    ->sales()
                    ->where('no', $invoiceNo)
                    ->value('id');
            }

            if (! $invoiceId) {
                throw ValidationException::withMessages([
                    'invoiceNo' => __('fields.sales_return_invoice_required'),
                ]);
            }
        }

        $formData = [
            'return_mode' => $returnMode,
            'invoice_id' => $invoiceId,
            'customer_id' => $payload['customerId'] ?? $payload['customer_id'] ?? null,
            'notes' => $notes,
            'payment_terms' => $paymentTerms,
            'prices_includes_taxes' => $pricesIncludesTaxes,
            'details' => $details,
            'credit_payment_ui' => $creditUi,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ];

        if ($paymentTerms === 'cash') {
            $formData['refund_acc4_code'] = '120100001';
        } else {
            $formData['refund_acc4_code'] = null;
        }

        return $formData;
    }

    /**
     * @param  array<int, array<string, mixed>>  $detailsInput
     * @return array<int, array<string, mixed>>
     */
    protected function buildDetails(
        string $returnMode,
        array $detailsInput,
        bool $pricesIncludesTaxes,
        int $customerId,
    ): array {
        $details = [];

        foreach ($detailsInput as $row) {
            if ($returnMode === 'customer') {
                $productKey = $row['productLineKey'] ?? $row['product_line_key'] ?? null;
                $qty = (float) ($row['qty'] ?? 0);

                if (! $productKey || $qty <= 0) {
                    continue;
                }

                $amounts = SalesReturnWorkflow::calculateProductReturnLineAmounts(
                    (string) $productKey,
                    $qty,
                    $pricesIncludesTaxes,
                    'sales',
                    $customerId ?: null,
                );

                $details[] = [
                    'product_line_key' => $productKey,
                    'qty' => $qty,
                    'price' => $amounts['price'] ?? 0,
                    'tax' => $amounts['tax'] ?? 0,
                    'discount' => $amounts['discount'] ?? 0,
                    'total' => $amounts['total'] ?? 0,
                ];

                continue;
            }

            $invoiceItemId = $row['invoiceItemId'] ?? $row['invoice_item_id'] ?? $row['id'] ?? null;
            $qty = (float) ($row['qty'] ?? 0);

            if (! $invoiceItemId || $qty <= 0) {
                continue;
            }

            /** @var InvoiceItem|null $item */
            $item = InvoiceItem::with('invoice')->find($invoiceItemId);

            if (! $item) {
                continue;
            }

            $amounts = SalesReturnWorkflow::calculateReturnLineAmounts($item, $qty, $pricesIncludesTaxes);

            $details[] = SalesReturnWorkflow::normalizeDetailForSave([
                'invoice_item_id' => $invoiceItemId,
                'qty' => $qty,
                'price' => $amounts['price'] ?? 0,
                'tax' => $amounts['tax'] ?? 0,
                'discount' => $amounts['discount'] ?? 0,
                'total' => $amounts['total'] ?? 0,
            ]);
        }

        if ($details === []) {
            throw ValidationException::withMessages([
                'details' => __('fields.table_empty_state'),
            ]);
        }

        return $details;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    protected function processCreditRefund(SalesReturns $record, array $formData, float $returnTotal): void
    {
        if (($formData['payment_terms'] ?? 'cash') !== 'credit') {
            return;
        }

        $ui = $formData['credit_payment_ui'] ?? [];
        $amount = SalesReturnWorkflow::normalizeCreditAmount($ui['credit_payment_amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        ReturnPaymentTermsService::instance()->recordSalesReturnCreditRefund($record, [
            'amount' => $amount,
            'account_code' => (string) ($ui['credit_payment_account'] ?? '120100001'),
            'date' => $ui['credit_payment_date'] ?? now()->toDateString(),
            'statement' => trim((string) ($ui['credit_payment_statement'] ?? '')),
        ]);
    }
}
