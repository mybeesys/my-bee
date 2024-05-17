<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends BaseResource
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
            'name' => $this->name,
            'phone' => $this->phone,
            'trn' => $this->trn,
            'deliveryAddress' => $this->delivery_address,
            'email' => $this->email,
            'state' => StateResource::make($this->state),
            'city' => CityResource::make($this->city),
            'area' => AreaResource::make($this->area),
            'acc4Code' => $this->acc4->code,
        ]);
    }
}
