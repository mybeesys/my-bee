<?php

namespace App\Http\Resources;

use App\Models\ReceiptVoucherPayment;

class InvoiceCreditPaymentResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var ReceiptVoucherPayment $payment */
        $payment = $this->resource;

        return $this->filterFields([
            'id' => $payment->id,
            'date' => $payment->date?->format('Y-m-d'),
            'dateFormatted' => $payment->date?->format('d/m/Y'),
            'amount' => number_format((float) $payment->amount, currency_decimals(), '.', ''),
            'amountNumeric' => round((float) $payment->amount, currency_decimals()),
            'statement' => $payment->statement,
            'currency' => main_currency_iso_code(),
        ]);
    }
}
