<?php

namespace App\Http\Requests\Concerns;

use Carbon\Carbon;

trait PreparesPriceOfferPayload
{
    protected function prepareForValidation(): void
    {
        if (! $this->exists('details') && $this->exists('items')) {
            $this->merge(['details' => $this->input('items')]);
        }

        $details = $this->input('details');

        if (is_array($details)) {
            foreach ($details as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                if (! array_key_exists('price', $line) && array_key_exists('unit_cost', $line)) {
                    $details[$index]['price'] = $line['unit_cost'];
                }

                if (! array_key_exists('extras', $line) && array_key_exists('product_extras_ids', $line)) {
                    $details[$index]['extras'] = $line['product_extras_ids'];
                }
            }

            $this->merge(['details' => $details]);
        }

        if ($this->exists('expires_at')) {
            $expiresAt = $this->input('expires_at');

            if ($expiresAt === '' || $expiresAt === null) {
                $this->merge(['expires_at' => null]);
            } elseif (is_string($expiresAt)) {
                $this->merge(['expires_at' => $this->normalizeDate($expiresAt)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function priceOfferLineRules(bool $required): array
    {
        $presence = $required ? 'required' : 'required_with:details';

        return [
            'details' => [$required ? 'required' : 'sometimes', 'array', 'min:1'],
            'details.*.product_id' => [$presence, 'integer', 'exists:products,id'],
            'details.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'details.*.selected_variant_options_ids' => ['sometimes', 'array'],
            'details.*.selected_variant_options_ids.*' => ['integer'],
            'details.*.qty' => [$presence, 'integer', 'min:1', 'max:250000'],
            'details.*.price' => [$presence, 'numeric', 'min:0.01', 'max:' . PHP_INT_MAX],
            'details.*.discount' => ['sometimes', 'numeric', 'min:0', 'max:' . PHP_INT_MAX],
            'details.*.tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
            'details.*.extras' => ['sometimes', 'array'],
            'details.*.extras.*' => ['integer', 'exists:product_extra,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function priceOfferExtraRules(): array
    {
        return [
            'prices_includes_taxes' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'discount_option' => ['sometimes', 'in:none,per-item,overall'],
            'discount_method' => ['sometimes', 'in:none,amount,percent'],
            'discount_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'services' => ['sometimes', 'array'],
            'services.*.service_type_id' => ['required', 'integer', 'exists:service_types,id'],
            'services.*.price' => ['required', 'numeric', 'min:0.01', 'max:' . PHP_INT_MAX],
            'services.*.description' => ['required', 'string', 'max:255'],
            'services.*.tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
            'additional_costs' => ['sometimes', 'array'],
            'additional_costs.*.additional_cost_type_id' => ['required', 'integer', 'exists:additional_cost_types,id'],
            'additional_costs.*.cost' => ['required', 'numeric', 'min:0.01', 'max:' . PHP_INT_MAX],
            'additional_costs.*.statement' => ['required', 'string', 'max:255'],
            'additional_costs.*.tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
        ];
    }

    protected function normalizeDate(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return $value;
    }
}
