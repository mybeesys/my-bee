<?php

namespace App\Http\Resources;

use App\Models\CashDet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashDetReportResource extends BaseResource
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
            'date' => $this->date->format('Y-m-d h:i A'),
            'statement' => $this->statement,
            'amountIn' => number_format($this->amount_in, currency_decimals(), ',', ''),
            'inFrom' => CashDet::with('account')->where('op_id', $this->op_id)->where('account_code', '!=', $this->account_code)?->first()->account?->name,
            'amountOut' => number_format($this->amount_out, currency_decimals(), ',', ''),
            'outTo' => CashDet::with('account')->where('op_id', $this->op_id)->where('account_code', '!=', $this->account_code)?->first()->account?->name,
            'balance' => number_format($this->balance_post_transaction, currency_decimals(), ',', ''),
        ]);
    }
}
