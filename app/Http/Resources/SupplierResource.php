<?php

namespace App\Http\Resources;

class SupplierResource extends BaseResource
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
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'trn' => $this->trn,
            'company' => $this->company,
            'notes' => $this->notes,
            'postalCode' => $this->postal_code,
            'address' => $this->address,
            'deliveryAddress' => $this->delivery_address ?: $this->address,
            'location' => $this->location,
            'stateId' => $this->city?->state_id ?? $this->state_id,
            'cityId' => $this->city_id,
            'areaId' => $this->area_id,
            'state' => $this->state ? StateResource::make($this->state) : ($this->city?->state ? StateResource::make($this->city->state) : null),
            'city' => $this->city ? CityResource::make($this->city) : null,
            'area' => $this->area ? AreaResource::make($this->area) : null,
            'acc4Code' => $this->acc4?->code,
            'supplyOrdersCount' => $this->when(isset($this->supply_orders_count), (int) $this->supply_orders_count),
            'purchaseInvoicesCount' => $this->when(isset($this->purchase_invoices_count), (int) $this->purchase_invoices_count),
            'canDelete' => true,
            'createdAt' => $this->created_at?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i:s'),
        ]);
    }
}
