<?php

namespace App\Http\Requests;

use Carbon\Carbon;

class CommitPurchaseInvoiceRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('date')) {
            $this->merge(['date' => $this->normalizeDate($this->input('date'))]);
        }

        $items = $this->input('items');

        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (! array_key_exists('unit_cost', $item) && array_key_exists('price', $item)) {
                    $items[$index]['unit_cost'] = $item['price'];
                }
            }

            $this->merge(['items' => $items]);
        }

        $credit = $this->input('credit_payment');

        if (is_array($credit) && ! empty($credit['date'])) {
            $credit['date'] = $this->normalizeDate($credit['date']);
            $this->merge(['credit_payment' => $credit]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'date' => ['sometimes', 'date', 'date_format:Y-m-d', 'after_or_equal:' . now()->subDays(30)->toDateString(), 'before_or_equal:today'],
            'payment_terms' => ['sometimes', 'in:cash,credit'],
            'prices_includes_taxes' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.selected_variant_options_ids' => ['sometimes', 'array'],
            'items.*.selected_variant_options_ids.*' => ['integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:' . PHP_INT_MAX],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0.01', 'max:' . PHP_INT_MAX],
            'items.*.discount' => ['sometimes', 'numeric', 'min:0', 'max:' . PHP_INT_MAX],
            'items.*.tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
            'additional_costs' => ['sometimes', 'array'],
            'additional_costs.*.additional_cost_type_id' => ['required', 'integer', 'exists:additional_cost_types,id'],
            'additional_costs.*.cost' => ['required', 'numeric', 'min:0.01', 'max:' . PHP_INT_MAX],
            'additional_costs.*.statement' => ['required', 'string', 'max:255'],
            'additional_costs.*.tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
            'credit_payment' => ['sometimes', 'nullable', 'array'],
            'credit_payment.account_code' => ['sometimes', 'nullable', 'exists:acc4,code'],
            'credit_payment.amount' => ['sometimes', 'numeric', 'min:0', 'max:' . PHP_INT_MAX],
            'credit_payment.date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'credit_payment.statement' => ['sometimes', 'nullable', 'string', 'max:255'],
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
