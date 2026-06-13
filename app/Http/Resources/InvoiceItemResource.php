<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
//        'subTotal' => format_amount(($this->price * $this->qty) + $this->tax - $this->discount) . " " . main_currency_native_symbol()

        return $this->filterFields([
            'id' => $this->id,
            'name' => $this->product->name,
            'qty' => $this->qty,
            'tax' => format_amount($this->tax) . " " . main_currency_native_symbol(),
            'discount' => format_amount($this->discount) . " " . main_currency_native_symbol(),
            'price' => format_amount($this->price) . " " . main_currency_native_symbol(),
            'subTotal' => format_amount(($this->price * $this->qty) - $this->discount) . " " . main_currency_native_symbol()
        ]);
    }
}
