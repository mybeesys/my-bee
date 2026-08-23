<?php

namespace App\Http\Resources;

use App\Models\PaymentVoucher;
use App\Models\Supplier;
use Carbon\Carbon;

class PaymentVoucherResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var PaymentVoucher $voucher */
        $voucher = $this->resource;
        $invoice = $voucher->invoice;
        $paidAmount = (float) $voucher->payments->sum('amount');
        $invoiceTotal = $invoice ? (float) $invoice->getItemsCost(true, true, true) : null;

        return $this->filterFields([
            'id' => $voucher->id,
            'no' => $voucher->no,
            'for' => $voucher->for,
            'invoiceId' => $invoice?->id,
            'invoiceNo' => $invoice?->no,
            'date' => Carbon::parse($voucher->date)->format('d-m-Y'),
            'dateFormatted' => Carbon::parse($voucher->date)->format('Y-m-d'),
            'userId' => $voucher->user?->id,
            'userName' => $voucher->user?->full_name,
            'createdAt' => $voucher->created_at->format('F j, Y, g:i a'),
            'createdAtFormatted' => $voucher->created_at?->format('Y-m-d H:i:s'),
            'createdAtDate' => $voucher->created_at?->format('d-m-Y'),
            'entityName' => $this->resolveEntityName($voucher),
            'supplierId' => $invoice?->supplier_id ?? Supplier::query()->whereRelation('acc4', 'code', $voucher->acc4_code)->value('id'),
            'paidAmount' => number_format($paidAmount, currency_decimals(), '.', ''),
            'paidAmountNumeric' => round($paidAmount, currency_decimals()),
            'invoiceTotal' => $invoiceTotal !== null ? number_format($invoiceTotal, currency_decimals(), '.', '') : null,
            'invoiceTotalNumeric' => $invoiceTotal !== null ? round($invoiceTotal, currency_decimals()) : null,
            'paidAmountPercent' => $invoiceTotal && $invoiceTotal > 0
                ? number_format(percent($paidAmount, $invoiceTotal), currency_decimals(), '.', '')
                : null,
            'acc4' => $voucher->acc4 ? new Acc4Resource($voucher->acc4) : null,
            'payments' => $this->paymentCollection(),
            'actions' => [
                'canEdit' => true,
            ],
        ]);
    }

    protected function resolveEntityName(PaymentVoucher $voucher): ?string
    {
        if ($voucher->for === 'supplier') {
            return $voucher->invoice?->supplier?->name
                ?? Supplier::query()->whereRelation('acc4', 'code', $voucher->acc4_code)->value('name');
        }

        return $voucher->acc4?->name;
    }

    protected function paymentCollection()
    {
        if ($this->invoice
            && $this->invoice->relationLoaded('purchasePayments')
            && $this->invoice->purchasePayments->isNotEmpty()) {
            return PaymentVoucherPaymentResource::collection($this->invoice->purchasePayments);
        }

        return PaymentVoucherPaymentResource::collection($this->payments);
    }
}
