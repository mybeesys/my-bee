<?php

namespace App\Http\Resources;


class SalesReturnsResource extends BaseResource
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
            'invoiceNo' => $this->invoice->no,
            'clientId' => $this->invoice->customer_id,
            'notes' => $this->notes,
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'items' => SalesReturnsDetailsResource::collection($this->details),
            'user' => new UserResource($this->user),
        ]);
    }
}
