<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentVoucherResource extends BaseResource
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
            'no' => $this->no,
            'for' => $this->for,
            'invoiceId' => $this->invoice?->id,
            'invoiceNo' => $this->invoice?->no,
            'date' => Carbon::parse($this->date)->format('d-m-Y'),
            'userId' => $this->user?->id,
            'userName' => $this->user?->full_name,
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'acc4' => new Acc4Resource($this->acc4),
            'payments' => PaymentVoucherPaymentResource::collection($this->payments),
        ]);
    }
}
