<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankReportResource extends BaseResource
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
            'accountCode' => $this->account_code,
            'date' => $this->date,
            'statement' => $this->statement,
            'amountIn' => number_format($this->amount_in, currency_decimals(), ',', ''),
            'amountOut' => number_format($this->amount_out, currency_decimals(), ',', ''),
            'balance' => number_format($this->balance_post_transaction, currency_decimals(), ',', ''),
        ]);
    }
}
