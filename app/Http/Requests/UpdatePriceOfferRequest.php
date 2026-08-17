<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesPriceOfferPayload;

class UpdatePriceOfferRequest extends BaseRequest
{
    use PreparesPriceOfferPayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'description' => ['sometimes', 'string', 'max:255'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
        ], $this->priceOfferLineRules(false), $this->priceOfferExtraRules());
    }
}
