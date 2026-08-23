<?php

namespace App\Http\Resources;

use App\Services\MediaService;
use Carbon\Carbon;

class PaymentVoucherPaymentResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'creditAcc4Name' => $this->creditAccount?->name,
            'creditAcc4Code' => $this->credit_acc4_code,
            'debitAcc4Name' => $this->debitAccount?->name,
            'debitAcc4Code' => $this->debit_acc4_code,
            'acc4Name' => $this->creditAccount?->name,
            'acc4Code' => $this->creditAccount?->code,
            'amount' => number_format((float) $this->amount, currency_decimals(), '.', ''),
            'amountNumeric' => round((float) $this->amount, currency_decimals()),
            'date' => Carbon::parse($this->date)->format('d-m-Y'),
            'dateFormatted' => Carbon::parse($this->date)->format('Y-m-d'),
            'statement' => $this->statement,
            'transactionCompleted' => (bool) $this->transaction_completed,
            'attachments' => MediaService::mediaUrls($this->getMedia('attachments')),
        ]);
    }
}
