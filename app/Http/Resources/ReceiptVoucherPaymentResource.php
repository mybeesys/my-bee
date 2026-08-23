<?php

namespace App\Http\Resources;

use App\Services\MediaService;
use Carbon\Carbon;

class ReceiptVoucherPaymentResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'debitAcc4Name' => $this->debitAccount?->name,
            'debitAcc4Code' => $this->debit_acc4_code,
            'creditAcc4Name' => $this->creditAccount?->name,
            'acc4Name' => $this->debitAccount?->name,
            'acc4Code' => $this->debit_acc4_code,
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
