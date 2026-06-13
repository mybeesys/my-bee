<?php

namespace App\Http\Resources;

use App\Services\MediaService;
use Carbon\Carbon;

class ReceiptVoucherPaymentResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'acc4Name' => $this->debitAccount->name,
            'acc4Code' => $this->creditAccount->code,
            'amount' => number_format($this->amount, currency_decimals(), '.', ''),
            'date' => Carbon::parse($this->date)->format('d-m-Y'),
            'statement' => $this->statement,
            'attachments' => MediaService::mediaUrls($this->getMedia('attachments')),
        ]);
    }
}
