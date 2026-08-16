<?php

namespace App\Http\Resources;

use App\Models\SupplyOrder;

class SupplierSupplyOrderListResource extends BaseResource
{
    /**
     * Compact supply-order row matching SupplyOrdersRelationManager.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var SupplyOrder $order */
        $order = $this->resource;

        return $this->filterFields([
            'id' => $order->id,
            'no' => $order->no,
            'description' => $order->description,
            'shareUrl' => $order->url,
            'createdAt' => $order->created_at?->format('Y-m-d H:i:s'),
        ]);
    }
}
