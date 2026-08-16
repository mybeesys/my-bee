<?php

namespace App\Http\Resources;

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
            'email' => $this->email,
            'trn' => $this->trn,
            'postalCode' => $this->postal_code,
            'deliveryAddress' => $this->delivery_address,
            'location' => $this->location,
            'stateId' => $this->state_id,
            'cityId' => $this->city_id,
            'areaId' => $this->area_id,
            'state' => $this->state ? StateResource::make($this->state) : null,
            'city' => $this->city ? CityResource::make($this->city) : null,
            'area' => $this->area ? AreaResource::make($this->area) : null,
            'acc4Code' => $this->acc4?->code,
            'ordersCount' => $this->when(isset($this->orders_count), (int) $this->orders_count),
            'invoicesCount' => $this->when(isset($this->invoices_count), (int) $this->invoices_count),
            'createdAt' => $this->created_at?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i:s'),
        ]);
    }
}
