<?php

namespace App\Http\Resources;

class PriceOfferResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $expired = $this->resource->isExpired();
        $detailsLoaded = $this->relationLoaded('details');

        return $this->filterFields([
            'id' => $this->id,
            'no' => $this->no,
            'customerId' => $this->customer_id,
            'description' => $this->description,
            'notes' => $this->notes,
            'date' => $this->created_at->format("d-m-Y"),
            'createdAt' => $this->created_at?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i:s'),
            'expiresAt' => $this->expires_at?->format('d-m-Y'),
            'expiresOn' => $this->expires_at?->format('Y-m-d'),
            'expired' => $expired,
            'expirationStatus' => $expired ? 'expired' : 'active',
            'expiredMessage' => $expired
                ? __('fields.price_offer_expired_client_message')
                : null,
            'shareUrl' => $this->url,
            'pricesIncludesTaxes' => (bool) $this->prices_includes_taxes,
            'discountOption' => $this->discount_option,
            'discountMethod' => $this->discount_method,
            'discountAmount' => $this->discount_amount,
            'discountPercent' => $this->discount_percent,
            'detailsCount' => $this->when(isset($this->details_count), (int) $this->details_count),
            'total' => $this->when($detailsLoaded, fn () => number_format($this->resource->getItemsCost(false, false, false), currency_decimals(), '.', ',')),
            'discount' => $this->when($detailsLoaded, fn () => number_format($this->resource->details->sum('discount'), currency_decimals(), '.', ',')),
            'totalAfterDiscount' => $this->when($detailsLoaded, fn () => number_format($this->resource->getItemsCost(true, true, true), currency_decimals(), '.', ',')),
            'tax' => $this->when($detailsLoaded, fn () => number_format($this->resource->getTaxesAsAmount(), currency_decimals(), '.', ',')),
            'totalWithTax' => $this->when($detailsLoaded, fn () => number_format($this->resource->getItemsCost(true, true, true), currency_decimals(), '.', ',')),
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($this->customer)),
            'products' => $this->whenLoaded('details', fn () => PriceOfferDetailsResource::collection($this->details)),
            'services' => $this->whenLoaded('services', fn () => ServiceResource::collection($this->services)),
            'additionalCosts' => $this->whenLoaded('additionalCosts', fn () => AdditionalCostResource::collection($this->additionalCosts)),
            'actions' => [
                'canShare' => true,
                'canConvertToSalesInvoice' => ! $expired,
                'canEdit' => true,
                'canDelete' => true,
            ],
        ]);
    }
}
